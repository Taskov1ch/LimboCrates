<?php

namespace Taskov1ch\LimboCrates\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\PluginOwnedTrait;
use Taskov1ch\LimboCrates\Main;

class DeleteCrateCommand extends Command implements PluginOwned
{
	use PluginOwnedTrait;

	private array $messages;

	public function __construct(Main $main, string $name, string $description)
	{
		parent::__construct($name, $description);
		$this->setPermission("limbo.crates.{$name}");
		$this->messages = $main->getMessages()["commands"][$name];
		$this->owningPlugin = $main;
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args): void
	{
		if (!$sender instanceof Player) {
			return;
		}

		/** @var Main */
		$main = $this->getOwningPlugin();

		if (count($args) !== 1) {
			$sender->sendMessage($this->messages["usage"]);
			return;
		}

		$attempt = $main->getCratesManager()->unregisterCrate($args[0]);
		$sender->sendMessage(str_replace(
			"{crate}",
			$args[0],
			$this->messages[$attempt ? "success" : "unsuccess"]
		));
	}
}
