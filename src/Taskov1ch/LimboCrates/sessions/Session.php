<?php

namespace Taskov1ch\LimboCrates\sessions;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\entity\object\ItemEntity;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\BlockEventPacket;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\Server;
use pocketmine\world\particle\ExplodeParticle;
use pocketmine\world\particle\FlameParticle;
use pocketmine\world\Position;
use Taskov1ch\LimboCrates\crates\Crate;
use Taskov1ch\LimboCrates\crates\Crates;
use Taskov1ch\LimboCrates\Main;
use WolfDen133\WFT\API\TextManager;
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
	private const FACING_MAP = [
		Facing::WEST => [0, 1],
		Facing::NORTH  => [2, 3],
		Facing::EAST => [4, 5],
		Facing::SOUTH  => [6, 7]
	];

	private array $messages;
	private TaskScheduler $scheduler;
	private TextManager $textManager;
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
			$adjust = fn (int $offset) => $offset + ($offset <=> 0) * Crates::OFFSET_STEP;
			$this->chestPositions[] = $crate->getPosition()->add($adjust($offsetX), 0, $adjust($offsetZ));
		}
		$this->shufflePositions = $this->chestPositions;
		$this->messages = Main::getInstance()->getMessages()["crate"];
		$this->scheduler = Main::getInstance()->getScheduler();
		$this->textManager = WFT::getInstance()->getTextManager();
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

	public function isTruePlayer(Player $player): bool
	{
		return $this->player !== null && $this->player->getName() === $player->getName();
	}

	public function getPlayer(): ?Player
	{
		return $this->player;
	}

	private function openChest(Vector3 $position): void
	{
		$pk = BlockEventPacket::create(BlockPosition::fromVector3($position), 1, 1);
		$world = $this->player->getWorld();
		$world->addParticle($position, new ExplodeParticle());
		$world->broadcastPacketToViewers($position, $pk);
	}

	private function chooseReward(): array
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

	private function startShuffle(): void
	{
		$this->shuffleTask = $this->scheduler->scheduleRepeatingTask(
			new ClosureTask(fn () => $this->player?->isOnline() ? $this->shuffle() : $this->close()),
			self::SHUFFLE_INTERVAL
		);

		$this->stopTask = $this->scheduler->scheduleDelayedTask(
			new ClosureTask(fn () => $this->stopShuffle()),
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
				0,
				0,
				0,
				0
			);

			foreach ($item->getViewers() as $viewer) {
				$viewer->getNetworkSession()->sendDataPacket($movePacket);
			}
		}

		$this->stopTask = $this->scheduler->scheduleDelayedTask(
			new ClosureTask(fn () => $this->prepareRewards()),
			self::PREPARE_REWARDS_DELAY
		);
	}

	private function prepareRewards(): void
	{
		$this->isChooseTime = true;
		$world = $this->player->getWorld();

		foreach ($this->chestPositions as $index => $position) {
			foreach (self::FACING_MAP as $facing => $indexes) {
				if (in_array($index, $indexes, true)) {
					$world->setBlock($position, VanillaBlocks::ENDER_CHEST()->setFacing($facing));
					break;
				}
			}
		}

		foreach ($this->items as $item) {
			$item->close();
		}

		$this->chooseTimeTask = $this->scheduler->scheduleRepeatingTask(
			new ClosureTask(function (): void {
				if ($this->chooseTime > 0) {
					$this->player?->sendTip(
						str_replace("{seconds}", (string) $this->chooseTime, $this->messages["tips"]["choose_time_left"])
					);
					$this->chooseTime--;
				} else {
					$this->reward($this->shufflePositions[0]);
				}
			}),
			20
		);
		$this->player?->sendTitle($this->messages["titles"]["select_crate"]);
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

	private function resetState(): void
	{
		$this->rewardFloatingText = $this->playerFloatingText = null;
		$this->startTask = $this->shuffleTask = $this->stopTask = $this->chooseTimeTask = null;
		$this->player = null;
		$this->isRunning = $this->isChooseTime = $this->isEnding = false;
		$this->shufflePositions = $this->chestPositions;
		$this->items = [];
	}

	private function reward(Vector3 $position): void
	{
		if ($this->isEnding) {
			return;
		}

		$this->isEnding = true;
		$this->chooseTimeTask?->remove();
		$this->openChest($position);

		$console = new ConsoleCommandSender(Server::getInstance(), Server::getInstance()->getLanguage());
		$selectedReward = $this->chooseReward();

		$this->player->sendTitle($selectedReward["name"], $this->messages["subtitles"]["ending"]);
		$name = $this->crate->getName();

		$this->rewardFloatingText = $this->textManager->registerText(
			"lc_{$name}_reward",
			$selectedReward["name"],
			new Position($position->getX() + 0.5, $position->getY() + 0.5, $position->getZ() + 0.5, $this->player->getWorld()),
			true,
			false
		);

		foreach ($selectedReward["commands"] as $command) {
			Server::getInstance()->dispatchCommand(
				$console,
				str_replace("{player}", $this->player->getName(), $command)
			);
		}

		$this->scheduler->scheduleDelayedTask(
			new ClosureTask(fn () => $this->close()),
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
			$this->player->sendMessage($this->messages["messages"]["not_choose_time"]);
			return;
		}

		$this->reward($block->getPosition());
	}

	public function handle(Player $player): void
	{
		if ($this->isRunning) {
			return;
		}

		$playerName = $player->getName();
		$name = $this->crate->getName();
		$tmpPosition = clone $this->crate->getPosition();
		$tmpPosition->x += 0.5;
		$tmpPosition->y += 2;
		$tmpPosition->z += 0.5;

		$this->playerFloatingText = $this->textManager->registerText(
			"lc_{$name}_player",
			str_replace("{player}", $playerName, $this->messages["texts"]["is_opening"]),
			$tmpPosition,
			true,
			false
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
			$this->items[] = $item;
		}

		$this->player->getNetworkSession()->sendDataPacket(
			PlaySoundPacket::create(
				"isolation.final_part",
				$position->getX(),
				$position->getY(),
				$position->getZ(),
				1,
				1
			)
		);

		$this->startTask = $this->scheduler->scheduleDelayedTask(
			new ClosureTask(fn () => $this->startShuffle()),
			self::START_DELAY
		);
	}

	public function close(bool $isForce = false, bool $serverShutdown = false): void
	{
		if ($isForce && $this->player !== null && !$this->isEnding) {
			Main::getInstance()->getKeysManager()->addKeys($this->player->getName(), 1, $serverShutdown);
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
			$this->textManager->removeText($this->rewardFloatingText->getName());
		}

		if ($this->playerFloatingText !== null) {
			$this->textManager->removeText($this->playerFloatingText->getName());
		}

		$this->startTask?->remove();
		$this->shuffleTask?->remove();
		$this->stopTask?->remove();
		$this->chooseTimeTask?->remove();
		$this->resetState();
	}
}
