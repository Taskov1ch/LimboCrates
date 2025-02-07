<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
	->in(__DIR__);

return (new Config())
	->setRiskyAllowed(true)
	->setIndent("\t")
	->setRules([
		"@PSR2" => true,
		"no_unused_imports" => true,
		"array_syntax" => ["syntax" => "short"],
		"no_trailing_whitespace_in_comment" => true,
		"no_empty_comment" => true,
		"no_extra_blank_lines" => ["tokens" => ["extra"]],
		"phpdoc_to_comment" => false,
		"no_trailing_whitespace" => true,
		"ordered_imports" => true,
	])
	->setFinder($finder);