<?php

class Pig_Config {
	static $config;

	// TODO: Flat-size the config?
	static function init($path) {
		if(file_exists($path)) {
			self::$config = parse_ini_file($path, true);
			if(self::$config == false)
				self::$config = array();
		}
		else
			self::$config = array();
	}

	// TESTING REASONS
	static function initFromString($str) {
		self::$config = parse_ini_string($str, true);
	}

	static function get($key, $default='') {
		$path = explode('.', $key);
		$value = self::$config;
		// Walk
		foreach($path as $part) {
			//echo "Testing for $part\n";
			if(!isset($value[$part])) {
				//echo "Not set\n";
				return $default;
			}
			if(!is_array($value[$part])) {
				//echo "Found {$value[$part]}\n";
				return $value[$part];
			}
			$value = $value[$part];
		}

		//if(isset(self::$config[$key]))
		//	return self::$config[$key];
		return $value;	// default?
	}
}

/*
// TEST CODE
$ini = <<<INI
foo=bar
[db]
host=hostname
port=3306
dbname=pigstats
[slim]
debug=1
mode=development
INI;

Pig_Config::initFromString($ini);
echo '<pre>';
print_r(Pig_Config::$config);
echo Pig_Config::get('foo') . PHP_EOL;
echo Pig_Config::get('boo') . PHP_EOL;
echo Pig_Config::get('db.host') . PHP_EOL;
echo Pig_Config::get('db.port') . PHP_EOL;
echo Pig_Config::get('slim.debug') . PHP_EOL;
echo Pig_Config::get('slim.foobar', 'default') . PHP_EOL;
echo print_r(Pig_Config::get('slim'),true) . PHP_EOL;
echo '</pre>';
*/