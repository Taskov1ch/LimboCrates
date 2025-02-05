<?php
declare(strict_types=1);

namespace Taskov1ch\LimboCrates\sessions;

use Closure;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\entity\object\ItemEntity;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\BlockEventPacket;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use pocketmine\Server;
use pocketmine\world\particle\ExplodeParticle;
use pocketmine\world\particle\FlameParticle;
use pocketmine\world\Position;
use Taskov1ch\LimboCrates\crates\Crate;
use Taskov1ch\LimboCrates\crates\Crates;
use Taskov1ch\LimboCrates\Main;
use WolfDen133\WFT\Texts\FloatingText;
use WolfDen133\WFT\WFT;

class Session
{
	private const START_DELAY = 20 * 5;
	private const SHUFFLE_INTERVAL = 5;
	private const STOP_DELAY = 20 * 9;
	private const PREPARE_REWARDS_DELAY = 20 * 2;
	private const CHOOSE_TIME = 10;
	private const END_TIME = 20 * 3;

	private bool $isRunning = false;
	private bool $isChooseTime = false;
	private bool $isEnding = false;
	private int $chooseTime = 0;
	private ?Player $player = null;
	private ?TaskHandler $startTask = null;
	private ?TaskHandler $shuffleTask = null;
	private ?TaskHandler $stopTask = null;
	private ?TaskHandler $chooseTimeTask = null;
	private ?FloatingText $rewardFloatingText = null;
	private ?FloatingText $playerFloatingText = null;

	/** @var Position[] */
	private array $chestPositions = [];

	/** @var ItemEntity[] */
	private array $items = [];

	/** @var Position[] */
	private array $shufflePositions = [];

	public function __construct(private Crate $crate)
	{
		foreach (Crates::OFFSETS as [$offsetX, $offsetZ]) {
			$adjust = fn(int $offset) => $offset + ($offset <=> 0) * Crates::OFFSET_STEP;
			$this->chestPositions[] = $crate->getPosition()->add($adjust($offsetX), 0, $adjust($offsetZ));
		}
		$this->shufflePositions = $this->chestPositions;
	}

	public function isRunning(): bool
	{
		return $this->isRunning;
	}

	public function isCrateChest(Block $block): bool
	{
		foreach ($this->chestPositions as $position) {
			if ($position->equals($block->getPosition())) {
				return true;
			}
		}
		return false;
	}

	private function getScheduler(): mixed
	{
		return Main::getInstance()->getScheduler();
	}

	private function openChest(Vector3 $position): void
	{
		$pk = BlockEventPacket::create(BlockPosition::fromVector3($position), 1, 1);
		$world = $this->player->getWorld();
		$world->addParticle($position, new ExplodeParticle());
		$world->broadcastPacketToViewers($position, $pk);
	}

	private function chooseReward(Vector3 $position): array
	{
		$rewards = $this->crate->getRewards();
		$totalChance = array_sum(array_column($rewards, "chance"));
		$random = mt_rand(1, $totalChance <= 0 ? 2 : $totalChance);

		foreach ($rewards as $reward) {
			$random -= $reward["chance"];

			if ($random <= 0) {
				return $reward;
			}
		}

		return Crates::DEFAULT_REWARD;
	}

	public function reward(Vector3 $position): void
	{
		if ($this->isEnding) {
			return;
		}

		$this->isEnding = true;
		$this->chooseTimeTask?->remove();
		$this->openChest($position);

		$console = new ConsoleCommandSender(Server::getInstance(), Server::getInstance()->getLanguage());
		$selectedReward = $this->chooseReward($position);

		$this->player->sendTitle($selectedReward["name"]);
		$name = $this->crate->getName();

		$this->rewardFloatingText = WFT::getInstance()->getTextManager()->registerText(
			"lc_{$name}_reward",
			$selectedReward["name"],
			new Position($position->getX() + 0.5, $position->getY() + 0.5, $position->getZ() + 0.5, $this->player->getWorld()),
			true,
			false
		);

		foreach ($selectedReward["commands"] as $command) {
			Server::getInstance()->dispatchCommand(
				$console, str_replace("{player}", $this->player->getName(), $command)
			);
		}

		$this->getScheduler()->scheduleDelayedTask(
			new ClosureTask(fn() => $this->close()),
			self::END_TIME
		);
	}

	public function handleChest(Player $player, Block $block): void
	{
		if (
			!$this->isCrateChest($block) || !$this->isRunning ||
			!$this->isTruePlayer($player) || $this->isEnding
		) {
			return;
		}

		if (!$this->isChooseTime) {
			$this->player->sendMessage("§cYou can't choose a reward now");
			return;
		}

		$this->player->sendMessage("You have chosen a reward!");
		$this->reward($block->getPosition());
	}

	public function handle(Player $player): void
	{
		if ($this->isRunning) {
			$player->sendMessage("§cYou can't open a crate now");
			return;
		}

		$playerName = $player->getName();
		$name = $this->crate->getName();
		$tmpPosition = clone $this->crate->getPosition();
		$tmpPosition->x += 0.5;
		$tmpPosition->y += 2;
		$tmpPosition->z += 0.5;

		$this->playerFloatingText = WFT::getInstance()->getTextManager()->registerText(
			"lc_{$name}_player", "Now opening: {$playerName}",
			$tmpPosition, true, false
		);

		$this->isRunning = true;
		$this->player = $player;
		$this->chooseTime = self::CHOOSE_TIME;

		foreach ($this->chestPositions as $position) {
			$item = $player->getWorld()->dropItem(
				$position->add(0.5, 1, 0.5),
				VanillaBlocks::ENDER_CHEST()->asItem(),
				new Vector3(0, 0, 0),
				9999
			);
			// $item->setNoClientPredictions(true);
			$this->items[] = $item;
		}

		$this->player->getNetworkSession()->sendDataPacket(
			PlaySoundPacket::create(
				"isolation.final_part",
				$position->getX(), $position->getY(), $position->getZ(),
				1, 1
			)
		);

		$this->startTask = $this->getScheduler()->scheduleDelayedTask(
			new ClosureTask(fn() => $this->startShuffle()),
			self::START_DELAY
		);
	}

	private function startShuffle(): void
	{
		$scheduler = $this->getScheduler();

		$this->shuffleTask = $scheduler->scheduleRepeatingTask(
			new ClosureTask(fn() => $this->player?->isOnline() ? $this->shuffle() : $this->close()),
			self::SHUFFLE_INTERVAL
		);

		$this->stopTask = $scheduler->scheduleDelayedTask(
			new ClosureTask(fn() => $this->stopShuffle()),
			self::STOP_DELAY
		);
	}

	private function stopShuffle(): void
	{
		$this->shuffleTask?->remove();

		foreach ($this->items as $index => $item) {
			$movePacket = MoveActorAbsolutePacket::create(
				$item->getId(),
				$this->shufflePositions[$index]->add(0.5, 0, 0.5),
				0, 0, 0, 0
			);

			foreach ($item->getViewers() as $viewer) {
				$viewer->getNetworkSession()->sendDataPacket($movePacket);
			}
		}

		$this->stopTask = $this->getScheduler()->scheduleDelayedTask(
			new ClosureTask(fn() => $this->prepareRewards()),
			self::PREPARE_REWARDS_DELAY
		);
	}

	private function prepareRewards(): void
	{
		$this->isChooseTime = true;
		$world = $this->player->getWorld();

		foreach ($this->chestPositions as $position) {
			$world->setBlock($position, VanillaBlocks::ENDER_CHEST());
		}

		foreach ($this->items as $item) {
			$item->close();
		}

		$this->chooseTimeTask = $this->getScheduler()->scheduleRepeatingTask(
			new ClosureTask(function (): void {
				if ($this->chooseTime > 0) {
					$this->player?->sendTip("{$this->chooseTime}s. left.");
					$this->chooseTime--;
				} else {
					$this->reward($this->shufflePositions[0]);
				}
			}),
			20
		);
		$this->player?->sendTitle("§aChoose a reward!");
	}

	private function shuffle(): void
	{
		shuffle($this->shufflePositions);

		foreach ($this->items as $index => $item) {
			$pos = $this->shufflePositions[$index]->add(0.5, mt_rand(0, 3), 0.5);
			$item->getWorld()->addParticle($pos, new FlameParticle());
			$movePacket = MoveActorAbsolutePacket::create($item->getId(), $pos, 0, 0, 0, 0);
			foreach ($item->getViewers() as $viewer) {
				$viewer->getNetworkSession()->sendDataPacket($movePacket);
			}
		}
	}

	public function close(bool $isForce = false, bool $serverShutdown = false): void
	{
		if ($isForce && $this->player !== null) {
			Main::getInstance()->getKeysManager()->addKeys($this->player, 1, $serverShutdown);
		}

		$world = $this->player?->getWorld();
		if ($world !== null) {
			foreach ($this->chestPositions as $position) {
				$world->setBlock($position, VanillaBlocks::AIR());
			}
		}

		foreach ($this->items as $item) {
			$item->close();
		}

		if ($this->rewardFloatingText !== null) {
			WFT::getInstance()->getTextManager()->removeText($this->rewardFloatingText->getName());
		}

		if ($this->playerFloatingText !== null) {
			WFT::getInstance()->getTextManager()->removeText($this->playerFloatingText->getName());
		}

		$this->startTask?->remove();
		$this->shuffleTask?->remove();
		$this->stopTask?->remove();
		$this->chooseTimeTask?->remove();
		$this->resetState();
	}

	private function resetState(): void
	{
		$this->rewardFloatingText = $this->playerFloatingText = null;
		$this->startTask = $this->shuffleTask = $this->stopTask = $this->chooseTimeTask = null;
		$this->player = null;
		$this->isRunning = $this->isChooseTime = $this->isEnding = false;
		$this->shufflePositions = $this->chestPositions;
		$this->items = [];
	}

	public function getPlayer(): ?Player
	{
		return $this->player;
	}

	public function isTruePlayer(Player $player): bool
	{
		return $this->player !== null && $this->player->getName() === $player->getName();
	}
}
