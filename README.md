## What is this?
[Here](https://youtu.be/IfdiEW4MiSo?si=GWGqiBXFklcBlo3w), [here](https://youtu.be/i7Me-RYVneM?si=MNHyf9rCYNzv5HMI), [here](https://youtu.be/pbY2v1Nf6N4?si=Zz7bQWrKrNCrhXFw), and [here](https://youtu.be/8vc2YoLWM0c?si=SoJvHpnj9c-jEWGR), too.

All of this is called **Final Part** from **Limbo - Geometry Dash**, also known as **Limbo Keys**.  

This plugin is based on this level mechanic.  

## Features  
- Key storage for opening crates is saved in a database, configurable in **config.yml**.  
- Key restoration if an unexpected event occurs while opening a crate (server shutdown, player disconnects before completion, etc.).  
- Ability to create an unlimited number of crates with different types and unique rewards.  
- Execution of reward commands as the console, eliminating the need for integration with other plugins.  
- Creation of floating texts *(see **Dependencies**).*  

## Commands  
|Command|Permission|Description|  
|:-:|:-:|:-:|  
|`addkeys <player: str> <value: int>`|`limbo.crates.addkeys`|Give a specific player a certain number of keys|  
|`takekeys <player: str> <value: int>`|`limbo.crates.talekeys`|Take away a certain number of keys from a player|  
|`mykeys`|`limbo.crates.mykeys` *(default)*|Check the number of keys you have|  
|`createcrate <name: str>`|`limbo.crates.createcrate`|Enter crate creation mode. Use the command again to exit the mode|  
|`deletecrate <name: str>`|`limbo.crates.deletecrate`|Delete a specific crate|  

## For Developers  
```php
// Example of working with player keys  

/** @var \Taskov1ch\LimboCrates\keys\Keys */  
$keys = Server::getInstance()->getPlugin("LimboCrates")->getKeysManager();  

/** @var string */  
$name = $player->getName();  

// Add keys  
$keys->addKeys($name, 999);  

// Take keys  
$keys->takeKeys($name, 999);  

// Get the number of keys (Promise)  
$keys->getKeys($name)->onCompletion(  
	fn (int $keys) => var_dump($keys),  
	fn () => var_dump("?")  
);  
```
```php
// Example of working with crates  

/** @var \Taskov1ch\LimboCrates\crates\Crates */  
$crates = Server::getInstance()->getPlugin("LimboCrates")->getCratesManager();  

// Register a crate  
$crates->registerCrate(  
	"example",  
	new Position(0, 4, 0, $world),  
	"Example Crate",  
	[  
		[  
			"name" => "Example Reward",  
			"chance" => 50,  
			"commands" => ["give {player} diamond 64"]  
		],  
		[  
			"name" => "Example Reward 2",  
			"chance" => 50,  
			"commands" => ["give {player} grass 64"]  
		]  
	]  
);  

// Unregister a crate  
$crates->unregisterCrate("example");  
```

## Dependencies  
- Plugin: [WFT](https://poggit.pmmp.io/p/WFT)  
- Virion (library): [libasynql](https://poggit.pmmp.io/ci/poggit/libasynql/libasynql)  

## Bugs and Issues  
No issues were found during testing, and any discovered were fixed. However, **[ISSUES](https://github.com/Taskov1ch/LimboCrates/issues)** is always open.  
