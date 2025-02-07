<?php

namespace Taskov1ch\LimboCrates\utils;

class StringUtils
{
	public static function steriliseString(string $string): string
	{
		$string = strtolower($string);
		$string = str_replace(" ", "_", strtolower($string));
		$string = preg_replace('/[^A-Za-z0-9_]/', "", $string);
		return preg_replace('/_+/', "_", $string);
	}
}
