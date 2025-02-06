<?php

namespace Taskov1ch\LimboCrates;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use Symfony\Component\Filesystem\Path;
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
		$this->getServer()->getPluginManager()->registerEvents(new EventsListener($this), $this);
		$this->initAll();
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
			$this->saveResource(Path::join("languages", $file->getFilename()));
		}

		$this->messages = (new Config(
			Path::join($langPath, $this->getConfig()->get("language") . ".yml")
		))->getAll();
		$this->crates = new Crates($this);
		$this->keys = new Keys($this);
	}
}
