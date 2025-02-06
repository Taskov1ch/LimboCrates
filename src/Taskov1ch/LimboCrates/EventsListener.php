<?php

namespace Taskov1ch\LimboCrates;

use pocketmine\block\Chest;
use pocketmine\block\EnderChest;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
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

		if ($block instanceof EnderChest && $crates->isCrateChest($block)) {
			$event->cancel();
			$crates->handleChest($player, $block);
			return;
		}

		$crate = $crates->getCrateByBlock($block);

		if ($crate !== null) {
			$event->cancel();
			$crate->handle($player);
		}
	}

	public function onBreak(BlockBreakEvent $event): void
	{
		$block = $event->getBlock();
		$crates = $this->main->getCratesManager();

		if ($crates->isCrateChest($block) || $crates->getCrateByBlock($block) !== null) {
			$event->cancel();
		}
	}
}
