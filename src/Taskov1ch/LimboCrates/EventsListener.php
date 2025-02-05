<?php

namespace Taskov1ch\LimboCrates;

use pocketmine\block\Chest;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;

class EventsListener implements Listener
{

	public function __construct(private Main $main)
	{}

	public function onQuit(PlayerQuitEvent $event): void
	{
		$player = $event->getPlayer();
		$this->main->getCratesManager()->removeSession($player);
	}

	public function onInteract(PlayerInteractEvent $event): void
	{
		$player = $event->getPlayer();
		$block = $event->getBlock();
		$crates = $this->main->getCratesManager();

		if ($block instanceof Chest && $crates->playerInSession($player)) {
			$event->cancel();
			$crates->handleChest($player, $block);
			return;
		}

		$crate = $crates->getCrateByBlock($block);

		if ($crate !== null) {
			$crate->handle($player);
		}
	}
}
