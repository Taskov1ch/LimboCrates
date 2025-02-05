<?php

namespace Taskov1ch\LimboCrates\crates;

use pocketmine\block\Block;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use pocketmine\world\World;
use Symfony\Component\Filesystem\Path;
use Taskov1ch\LimboCrates\Main;

class Crates
{
	public const OFFSET_STEP = 1;
	public const OFFSETS = [
		[2, -1],
		[2, 1],
		[1, 2],
		[-1, 2],
		[-2, 1],
		[-2, -1],
		[-1, -2],
		[1, -2],
	];

	/** @var Crate[] */
	private array $crates = [];

	private Config $cratesFile;

	public function __construct(private Main $main)
	{
		$this->cratesFile = new Config(Path::join($main->getDataFolder(), "crates.yml"));
		$this->loadAll();
	}

	private function ensureWorldLoaded(string $worldName): ?World
	{
		$worldManager = Server::getInstance()->getWorldManager();
		if (!$worldManager->isWorldLoaded($worldName)) {
			$worldManager->loadWorld($worldName);
		}
		return $worldManager->getWorldByName($worldName);
	}

	private function loadCrate(string $name, array $data): void
	{
		if (!isset($data["world"])) {
			$this->main->getLogger()->critical("Missing 'world' key for crate '$name'");
			return;
		}

		$world = $this->ensureWorldLoaded($data["world"]);

		if ($world === null) {
			$this->main->getLogger()->critical("World '{$data["world"]}' not found for crate '$name'");
			return;
		}

		if (isset($data["position"]) && $data["position"] instanceof Position) {
			$position = $data["position"];
		} elseif (isset($data["x"], $data["y"], $data["z"])) {
			$position = new Position((int)$data["x"], (int)$data["y"], (int)$data["z"], $world);
		} else {
			$this->main->getLogger()->critical("Missing position data for crate '$name'");
			return;
		}

		$title = $data["title"] ?? $name;
		$rewards = $data["rewards"] ?? [];

		$this->crates[$name] = new Crate($this, $name, $position, $title, $rewards);
	}

	private function loadAll(): void
	{
		foreach ($this->cratesFile->getAll() as $name => $data) {
			if (is_array($data)) {
				$this->loadCrate($name, $data);
			} else {
				$this->main->getLogger()->warning("Invalid data format for crate '$name'");
			}
		}

		$this->main->getLogger()->info(TextFormat::AQUA . "Loaded " . TextFormat::GOLD . count($this->crates) . TextFormat::AQUA . " crates" . TextFormat::RESET);
	}

	public function saveAll(): void
	{
		foreach ($this->crates as $name => $crate) {
			$pos = $crate->getPosition();
			$this->cratesFile->set($name, [
				"x" => $pos->getFloorX(),
				"y" => $pos->getFloorY(),
				"z" => $pos->getFloorZ(),
				"world" => $pos->getWorld()->getFolderName(),
				"title" => $crate->getTitle(),
				"rewards" => $crate->getRewards()
			]);
		}

		$this->cratesFile->save();
	}

	public function closeAll(): void
	{
		foreach ($this->crates as $crate) {
			$crate->close();
		}
	}

	public function getAll(): array
	{
		return $this->crates;
	}

	public function registerCrate(string $name, Position $position, ?string $title = null, array $rewards = []): void
	{
		$data = [
			"position" => $position,
			"world" => $position->getWorld()->getFolderName(),
			"title" => $title ?? $name,
			"rewards" => $rewards
		];
		$this->loadCrate($name, $data);
	}

	public function getCrate(string $name): ?Crate
	{
		return $this->crates[$name] ?? null;
	}

	public function getCrateByBlock(Block $block): ?Crate
	{
		$blockPos = $block->getPosition();

		foreach ($this->crates as $crate) {
			if ($crate->getPosition()->equals($blockPos)) {
				return $crate;
			}
		}

		return null;
	}

	public function handleChest(Player $player, Block $block): void
	{
		foreach ($this->crates as $_ => $crate) {
			$crate->getSession()->handleChest($player, $block);
		}
	}

	public function removeSession(Player $player): void
	{
		foreach ($this->crates as $_ => $crate) {
			if ($crate->getSession()->getPlayer() === $player) {
				$crate->getSession()->close();
			}
		}
	}

	public function playerInSession(Player $player): bool
	{
		foreach ($this->crates as $_ => $crate) {
			if ($crate->getSession()->getPlayer() === $player) {
				return true;
			}
		}

		return false;
	}
}
