<?php

namespace Taskov1ch\LimboCrates;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use Taskov1ch\LimboCrates\crates\Crates;
use Taskov1ch\LimboCrates\keys\Keys;

class Main extends PluginBase
{
	use SingletonTrait;

	private Crates $crates;
	private Keys $keys;

	public function onLoad(): void
	{
		self::setInstance($this);
	}

	public function onEnable(): void
	{
		$this->getServer()->getPluginManager()->registerEvents(new EventsListener($this), $this);
		$this->crates = new Crates($this);
		$this->keys = new Keys($this);
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
}
