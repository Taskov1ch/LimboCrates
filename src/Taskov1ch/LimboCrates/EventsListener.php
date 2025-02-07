<?php

namespace Taskov1ch\LimboCrates;

use pocketmine\block\EnderChest;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerQuitEvent;
use Taskov1ch\LimboCrates\crates\CreateCrateStates;

class EventsListener implements Listener
{
	private array $messages;

	public function __construct(private Main $main)
	{
		$this->messages = $main->getMessages();
	}

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

		if (CreateCrateStates::inState($player)) {
			$crate = CreateCrateStates::getStateName($player);

			$attempt = $crates->registerCrate($crate, $block->getPosition());
			$player->sendMessage(
				str_replace(
					"{crate}",
					$crate,
					$this->messages["commands"]["createcrate"][$attempt ? "success" : "unsuccess"]
				)
			);
			CreateCrateStates::removeState($player);
			return;
		}

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
