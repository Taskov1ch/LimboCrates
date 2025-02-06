<?php

namespace Taskov1ch\LimboCrates;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use Symfony\Component\Filesystem\Path;
use Taskov1ch\LimboCrates\commands\AddKeysCommand;
use Taskov1ch\LimboCrates\commands\CreateCrateCommand;
use Taskov1ch\LimboCrates\commands\DeleteCrateCommand;
use Taskov1ch\LimboCrates\commands\MyKeysCommand;
use Taskov1ch\LimboCrates\commands\TakeKeysCommand;
use Taskov1ch\LimboCrates\crates\Crates;
use Taskov1ch\LimboCrates\keys\Keys;

class Main extends PluginBase
{
	use SingletonTrait;

	private Crates $crates;
	private Keys $keys;
	private array $messages;

	public function onLoad(): void
	{
		self::setInstance($this);
	}

	public function onEnable(): void
	{
		$this->initAll();
		$this->registerCommands();
		$this->getServer()->getPluginManager()->registerEvents(new EventsListener($this), $this);
	}

	public function onDisable(): void
	{
		$this->crates->closeAll();
	}

	public function getCratesManager(): Crates
	{
		return $this->crates;
	}

	public function getKeysManager() : Keys
	{
		return $this->keys;
	}

	public function getMessages(): array
	{
		return $this->messages;
	}

	private function initAll(): void
	{
		$langPath = Path::join($this->getDataFolder(), "languages");

		if (!is_dir($langPath)) {
			mkdir($langPath);
		}

		foreach ($this->getResources() as $file) {
			$this->saveResource(Path::join(
				basename($file->getPath()) === "languages" ? "languages" : "",
				$file->getBasename()
			));
		}

		$this->messages = (new Config(
			Path::join($langPath, $this->getConfig()->get("language") . ".yml"))
		)->getAll();
		$this->crates = new Crates($this);
		$this->keys = new Keys($this);
	}

	private function registerCommands(): void
	{
		$commands = [
			"addkeys" => new AddKeysCommand($this, "addkeys", "Add keys to the player."),
			"takekeys" => new TakeKeysCommand($this, "takekeys", "Take away the keys to the player."),
			"mykeys" => new MyKeysCommand($this, "mykeys", "Find out how many keys you have."),
			"createcrate" => new CreateCrateCommand($this, "createcrate", "Create a crate."),
			"deletecrate" => new DeleteCrateCommand($this, "deletecrate", "Delete a crate.")
		];

		$this->getServer()->getCommandMap()->registerAll("LimboCrates", $commands);
	}
}
