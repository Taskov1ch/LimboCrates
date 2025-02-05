<?php

namespace Taskov1ch\LimboCrates\crates;

use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\Position;
use Taskov1ch\LimboCrates\Main;
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

	public function getName(): string
	{
		return $this->name;
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

		if (Server::getInstance()->isOp($player->getName())) {
			$this->session->handle($player);
			return;
		}

		Main::getInstance()->getKeysManager()->getKeys($player)->onCompletion(
			function(int $keys) use($player) {
				if ($keys <= 0) {
					$player->sendMessage("§cKeys 0 :(");
				} else {
					Main::getInstance()->getKeysManager()->takeKeys($player, 1);
					$this->session->handle($player);
				}
			},
			fn () => null
		);
	}

	public function close($isForce = false): void
	{
		$this->session->close($isForce);
	}
}
