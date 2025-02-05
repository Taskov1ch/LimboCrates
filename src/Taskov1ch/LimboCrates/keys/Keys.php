<?php

namespace Taskov1ch\LimboCrates\keys;

use pocketmine\player\Player;
use pocketmine\promise\Promise;
use pocketmine\promise\PromiseResolver;
use Taskov1ch\LimboCrates\libs\poggit\libasynql\DataConnector;
use Taskov1ch\LimboCrates\libs\poggit\libasynql\libasynql;
use Taskov1ch\LimboCrates\Main;

class Keys
{
	private DataConnector $db;

	public function __construct(private Main $main)
	{
		$this->db = libasynql::create($main, $main->getConfig()->get("database"), [
			"sqlite" => "database/sqlite.sql",
			"mysql" => "database/mysql.sql"
		]);
		$this->db->executeGeneric("keys.init");
		$this->db->waitAll();
	}

	private function guarantee(string $player): Promise
	{
		$promise = new PromiseResolver();
		$this->db->executeInsert(
			"keys.guarantee",
			compact("player"),
			fn() => $promise->resolve(true)
		);
		return $promise->getPromise();
	}

	private function updateKeys(Player $player, int $keys, string $query, bool $wait = false): void
	{
		$player = strtolower($player->getName());
		$this->guarantee($player)->onCompletion(
			function() use($player, $keys, $query): void {
				$this->db->executeChange($query, compact("player", "keys"));
			},
			fn() => null
		);

		if ($wait) {
			$this->db->waitAll();
		}
	}

	public function getKeys(Player $player): Promise
	{
		$player = strtolower($player->getName());
		$promise = new PromiseResolver();

		$this->guarantee($player)->onCompletion(
			function(?bool $success = null) use($player, $promise) {
				$this->db->executeSelect(
					"keys.get",
					compact("player"),
					fn(array $rows) => $success ? $promise->resolve($rows[0]["keys"]) : $promise->reject(),
					fn() => $promise->reject()
				);
			},
			fn () => $promise->reject()
		);

		return $promise->getPromise();
	}

	public function addKeys(Player $player, int $keys, bool $wait = false): void
	{
		$this->updateKeys($player, $keys, "keys.add", $wait);
	}

	public function takeKeys(Player $player, int $keys, bool $wait = false): void
	{
		$this->updateKeys($player, $keys, "keys.take", $wait);
	}

	public function setKeys(Player $player, int $keys, bool $wait = false): void
	{
		$this->updateKeys($player, $keys, "keys.set", $wait);
	}
}
