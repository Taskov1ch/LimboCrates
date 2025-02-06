<?php

namespace Taskov1ch\LimboCrates\crates;

use pocketmine\player\Player;

class CreateCrateStates
{
	private static array $states = [];

	public static function inState(Player $player): bool
	{
		$name = strtolower($player->getName());
		return in_array($name, self::$states);
	}

	public static function getStateName(Player $player): ?string
	{
		return self::inState($player) ? array_search(strtolower($player->getName()), self::$states) : null;
	}

	public static function removeState(Player $player): void
	{
		if (!self::inState($player)) {
			return;
		}

		$name = strtolower($player->getName());
		unset(self::$states[self::getStateName($player)]);
	}

	public static function addState(Player $player, string $name): void
	{
		if (self::inState($player)) {
			self::removeState($player);
		}

		self::$states[$name] = strtolower($player->getName());
	}
}