<?php

// YAAL -> Yet Another AutoLoader
class Pig_Autoloader {
	public static function init() {
		spl_autoload_register(function($class) {
			// debug('spl_autoload_register: ' . $class);
			// Special feature.. for class Class, include Class/Class.php (or Class/index.php?)
			if(strpos($class, '\\') != false)
				$path = $class . '.php';
			//else if(strpos($class, '_') == false)
			//	$path = $class . DIRECTORY_SEPARATOR . $class . '.php';
			else
				$path = str_replace('_', DIRECTORY_SEPARATOR, $class) . '.php';
			include $path;
		});
	}

	// Other features

	/*
	Logic:
		We are supporting both styles of Path_Class and Path\Class, but to verify that the directory exists, we need to
		scan all directories in include path

		$subpath = ^^
		$paths = explode(PATH_SEPARATOR, get_include_path());
		foreach($paths as $path) {
			
		}
	*/
}

// Dirty hack but thats OK, since require 'Pig/Autoloader' is a must in here,
// this comes in automagically!
function debug() {
	if(defined('PIG_ENVIRONMENT') && PIG_ENVIRONMENT != 'devel')
		return;
	if(defined('PIG_DEBUG') && PIG_DEBUG != true)
		return;
	$args = func_get_args();
	foreach($args as $arg) {
		if(is_object($arg) || is_array($arg))
			$arg = print_r($arg, true);
		echo "<pre>{$arg}</pre>\n";
	}
}