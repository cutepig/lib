<?php

class Pig_Config {
	static $config = null;

	static public function init($path, $section=false) {
		if(file_exists($path))
			self::initFromArray(parse_ini_file($path, true), $section);
		else
			self::$config = array();
	}

	static public function initFromString($str, $section=false) {
		self::initFromArray(parse_ini_string($str, true), $section);
	}

	// Actual logic in breaking down the sections and choosing the right one
	static public function initFromArray($arr, $section=false) {
		if($section) {
			$newconf = array();
			foreach($arr as $_section => $content) {
				if(is_array($content) && !isset($content[0])) {	// Check against key[] = value
					// Check for inheritance
					$m = array();
					if(preg_match('/([\w]+)[\W]*:[\W]*([\w]+)/', $_section, $m)) {
						$child = $m[1];
						$parent = $m[2];
						
						$newconf[$child] = unserialize(serialize($newconf[$parent]));
						$_section = $child;
					}
					else {
						// $newconf[$_section] = unserialize(serialize($config[$_section]));
					}

					//Iterate against the keys and add or merge them to this section
					foreach($content as $key => $value) {
						$newconf[$_section][$key] = unserialize(serialize($value));
					}
				}
				else {
					// Iterate the global keys and add or merge them to this section
					$newconf[$_section] = unserialize(serialize($content));
				}
			}

			// Pick the globals and given section now that we have merged each section
			self::$config = array();
			foreach($newconf as $_section => $content) {
				// If this a section and its a section we want
				if(is_array($content) && !isset($content[0])) {
					if($_section == $section) {
						foreach($content as $key => $value)
							self::$config[$key] = $value;
					}
				}
				else {
					// Global key
					self::$config[$_section] = $content;
				}
			}
		}
		else {
			// No section picking, everything goes
			self::$config = unserialize(serialize($arr));
		}
	}

	static public function get($key, $default=false) {
		if(isset(self::$config[$key]))
			return self::$config[$key];
		return $default;
	}

	static public function set($key, $value) {
		self::$config[$key] = $value;
	}

	static public function get2($key, $default='') {
		if(!self::$config)
			return $default;
		$path = explode('/', $key);
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

	static function debug() {
		print_r(self::$config);
	}
}

/*
// TEST CODE
// TODO: Update to the new Config way
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