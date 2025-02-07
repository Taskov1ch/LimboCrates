<?php

namespace Taskov1ch\LimboCrates\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\PluginOwnedTrait;
use Taskov1ch\LimboCrates\Main;

class TakeKeysCommand extends Command implements PluginOwned
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
		/** @var Main */
		$main = $this->getOwningPlugin();

		if (count($args) !== 2 || !ctype_digit($args[1])) {
			$sender->sendMessage($this->messages["usage"]);
			return;
		}

		$main->getKeysManager()->takeKeys(
			$args[0],
			$args[1],
			false,
			fn () => $sender->sendMessage(str_replace(
				["{player}", "{keys}"],
				[$args[0], $args[1]],
				$this->messages["success"]
			))
		);
	}
}
