<?php

namespace Taskov1ch\LimboCrates\sessions;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\object\ItemEntity;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use pocketmine\Server;
use pocketmine\world\particle\FlameParticle;
use pocketmine\world\Position;
use Taskov1ch\LimboCrates\crates\Crate;
use Taskov1ch\LimboCrates\crates\Crates;
use Taskov1ch\LimboCrates\Main;
use WolfDen133\WFT\Texts\FloatingText;

class Session
{
	private bool $isRunning = false;
	private bool $isChooseTime = false;
	private ?Player $player = null;
	private ?TaskHandler $startTask = null;
	private ?TaskHandler $shuffleTask = null;
	private ?TaskHandler $stopTask = null;
	private ?TaskHandler $timeToChooseTask = null;

	/** @var Position[] */
	private array $chestPotitions = [];

	/** @var ItemEntity[] */
	private array $items = [];

	/** @var Position[] */
	private array $tmpPositions = [];

	public function __construct(private Crate $crate)
	{
		foreach (Crates::OFFSETS as [$offsetX, $offsetZ]) {
			$adjustedX = $offsetX + ($offsetX <=> 0) * Crates::OFFSET_STEP;
			$adjustedZ = $offsetZ + ($offsetZ <=> 0) * Crates::OFFSET_STEP;
			$this->chestPotitions[] = $crate->getPosition()->add($adjustedX, 0, $adjustedZ);
		}

		$this->tmpPositions = $this->chestPotitions;
	}

	public function isRunning(): bool
	{
		return $this->isRunning;
	}

	public function isCrateChest(Block $block): bool
	{
		foreach ($this->chestPotitions as $position) {
			if ($position->equals($block->getPosition())) {
				return true;
			}
		}

		return false;
	}

	public function close(): void
	{
		foreach ($this->chestPotitions as $position) {
			$this->player?->getWorld()->setBlock($position, VanillaBlocks::AIR());
		}

		foreach ($this->items as $item) {
			$item->close();
		}

		if ($this->startTask) {
			$this->startTask->remove();
			$this->startTask = null;
		}

		if ($this->shuffleTask) {
			$this->shuffleTask->remove();
			$this->shuffleTask = null;
		}

		if ($this->stopTask) {
			$this->stopTask->remove();
			$this->stopTask = null;
		}

		$this->tmpPositions = $this->chestPotitions;
		$this->items = [];
		$this->player = null;
		$this->isRunning = false;
		$this->isChooseTime = false;
		// delete song
	}

	public function getPlayer(): ?Player
	{
		return $this->player;
	}

	public function isTruePlayer(Player $player): bool
	{
		return $this->player->getName() === $player->getName();
	}

	public function handleChest(Player $player, Block $block): void
	{
		if (!$this->isCrateChest($block) || !$this->isRunning || !$this->isTruePlayer($player)) {
			// var_dump($this->isCrateChest($block), $this->isRunning, $this->isTruePlayer($player), "===");
			return;
		}

		if (!$this->isChooseTime) {
			$this->player->sendMessage("§cYou can't choose a reward now");
			return;
		}

		$this->player->sendMessage("You have chosen a reward!");
		$this->close();
	}

	public function handle(Player $player): void
	{
		if ($this->isRunning) {
			$player->sendMessage("§cYou can't open a crate now");
			return;
		}

		$this->isRunning = true;
		$this->player = $player;

		$player->sendMessage("You have opened a {$this->crate->getTitle()} crate!");

		foreach ($this->chestPotitions as $position) {
			$item = $player->getWorld()->dropItem($position->add(0.5, 1, 0.5), VanillaBlocks::CHEST()->asItem(), new Vector3(0, 0, 0), 9999);
			$item->setNoClientPredictions(true);
			$this->items[] = $item;
		}

		$pk = PlaySoundPacket::create(
			"isolation.final_part",
			$player->getPosition()->getX(),
			$player->getPosition()->getY(),
			$player->getPosition()->getZ(),
			1, 1
		);
		$player->getNetworkSession()->sendDataPacket($pk);

		$this->startTask = Main::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
			fn() => $this->startShuffle()
		), 20 * 4);
	}

	private function startShuffle(): void
	{
		$this->shuffleTask = Main::getInstance()->getScheduler()->scheduleRepeatingTask(new ClosureTask(
			fn() => $this->player->isOnline() ? $this->shuffle() : $this->close()
		), 8);
		$this->stopTask = Main::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
			fn() => $this->stopShuffle()
		), 20 * 10);
	}

	private function stopShuffle(): void
	{
		$this->shuffleTask->remove();

		foreach ($this->items as $key => $item) {
			$pos = $this->tmpPositions[$key]->add(0.5, 1, 0.5);
			$pk = MoveActorAbsolutePacket::create($item->getId(), $pos, 0, 0, 0, 0);

			foreach ($item->getViewers() as $player) {
				$player->getNetworkSession()->sendDataPacket($pk);
			}
		}

		$this->stopTask = Main::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
			fn() => $this->prepareRewards()
		), 20 * 2);
	}

	private function prepareRewards(): void
	{
		$this->isChooseTime = true;

		foreach ($this->chestPotitions as $position) {
			$this->player->getWorld()->setBlock($position, VanillaBlocks::CHEST());
		}

		foreach ($this->items as $item) {
			$item->close();
		}

		$this->player->sendTitle("§aChoose a reward!");
	}

	private function shuffle(): void
	{
		shuffle($this->tmpPositions);

		foreach ($this->items as $key => $item) {
			$pos = $this->tmpPositions[$key]->add(0.5, mt_rand(0, 3), 0.5);
			$item->getWorld()->addParticle($pos, new FlameParticle());
			$pk = MoveActorAbsolutePacket::create($item->getId(), $pos, 0, 0, 0, 0);

			foreach ($item->getViewers() as $player) {
				$player->getNetworkSession()->sendDataPacket($pk);
			}
		}
	}

}