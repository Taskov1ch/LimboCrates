<?php

namespace Taskov1ch\LimboCrates\crates;

use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\Position;
use Taskov1ch\LimboCrates\Main;
use Taskov1ch\LimboCrates\sessions\Session;
use WolfDen133\WFT\Texts\FloatingText;
use WolfDen133\WFT\WFT;

class Crate
{
	private Session $session;
	private array $messages;
	private FloatingText $floatingText;

	public function __construct(
		private Crates $cases,
		private string $name,
		private Position $position,
		private string $title,
		private array $rewards
	) {
		$tmpPos = clone $position;
		$tmpPos->x += 0.5;
		$tmpPos->y += 1;
		$tmpPos->z += 0.5;

		$this->floatingText = WFT::getInstance()->getTextManager()->registerText(
			"lc_{$name}",
			$title,
			$tmpPos,
			true,
			false
		);
		$this->session = new Session($this);
		$this->messages = Main::getInstance()->getMessages()["crate"]["messages"];
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
			if ($this->session->isTruePlayer($player)) {
				$player->sendMessage($this->messages["crate_opening"]);
			} else {
				$player->sendMessage($this->messages["crate_busy"]);
			}

			return;
		}

		if (Server::getInstance()->isOp($player->getName())) {
			$this->session->handle($player);
			return;
		}

		Main::getInstance()->getKeysManager()->getKeys($player->getName())->onCompletion(
			function (int $keys) use ($player) {
				if ($keys <= 0) {
					$player->sendMessage($this->messages["insufficient_keys"]);
				} else {
					Main::getInstance()->getKeysManager()->takeKeys($player->getName(), 1);
					$this->session->handle($player);
				}
			},
			fn () => null
		);
	}

	public function close($isForce = false, bool $serverShutdown = false): void
	{
		WFT::getInstance()->getTextManager()->removeText($this->floatingText->getName());
		$this->session->close($isForce, $serverShutdown);
	}
}
