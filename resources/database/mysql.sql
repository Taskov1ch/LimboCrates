-- #! mysql

-- #{ keys
	-- #{ init
		CREATE TABLE IF NOT EXISTS keys (
			player TEXT PRIMARY KEY,
			keys INT DEFAULT 0
		);
	-- #}

	-- #{ get
		-- # :player string
		INSERT INTO keys (player) SELECT :player WHERE NOT EXISTS (SELECT 1 FROM keys WHERE player = :player);
		SELECT keys FROM keys WHERE player = :player;
	-- #}

	-- #{ add
		-- # :player string
		-- # :keys int
		INSERT INTO keys (player) SELECT :player WHERE NOT EXISTS (SELECT 1 FROM keys WHERE player = :player);
		UPDATE keys SET keys = keys + :keys WHERE player = :player;
	-- #}

	-- #{ take
		-- # :player string
		-- # :keys int
		INSERT INTO keys (player) SELECT :player WHERE NOT EXISTS (SELECT 1 FROM keys WHERE player = :player);
		UPDATE keys SET keys = keys - :keys WHERE player = :player;
	-- #}

	-- #{ set
		-- # :player string
		-- # :keys int
		INSERT INTO keys (player) SELECT :player WHERE NOT EXISTS (SELECT 1 FROM keys WHERE player = :player);
		UPDATE keys SET keys = :keys WHERE player = :player;
	-- #}
-- #}