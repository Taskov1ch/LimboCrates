<?php

namespace Taskov1ch\LimboCrates\crates;

use pocketmine\block\Block;
use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\block\VanillaBlocks;
use Taskov1ch\LimboCrates\sessions\Session;
use WolfDen133\WFT\WFT;

class Crate
{
	private Session $session;

	public function __construct(
		private Crates $cases,
		private string $name,
		private Position $position,
		private string $title,
		private array $rewards
	) {
		$tmpPosition = clone $position;
		$tmpPosition->x += 0.5;
		$tmpPosition->y += 1;
		$tmpPosition->z += 0.5;
		WFT::getInstance()->getTextManager()->registerText("lc_{$name}", $title, $tmpPosition, true, false);
		$this->session = new Session($this);
	}

	public function getPosition(): Position
	{
		return $this->position;
	}

	public function getTitle(): string
	{
		return $this->title;
	}

	public function getRewards(): array
	{
		return $this->rewards;
	}

	public function getSession(): Session
	{
		return $this->session;
	}

	public function handle(Player $player): void
	{
		if ($player->getWorld() !== $this->position->getWorld()) {
			return;
		}

		if ($this->session->isRunning()) {
			$player->sendMessage("§cYou can't open this crate now");
			return;
		}

		$this->session->handle($player);
	}

	public function close(): void
	{
		$this->session->close();
	}
}