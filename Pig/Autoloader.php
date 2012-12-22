<?php

// YAAL -> Yet Another AutoLoader
class Pig_Autoloader {
	public static function init($namespaces=null) {
		$nss = $namespaces ? unserialize(serialize($namespaces)) : null;
		spl_autoload_register(function($class) use ($nss) {
			// Check if we have registered namespaces and then check against them.
			// Otherwise autoload everything
			if($nss) {
				$found = false;
				foreach($nss as $ns) {
					if(substr($class, 0, strlen($ns)) == $ns) {
						$found = true;
						break;
					}
				}
				if(!$found)
					return;
			}
			// debug('spl_autoload_register: ' . $class);
			// "New way" class naming uses slashes instead of underscores
			if(strpos($class, '\\') != false)
				$path = str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
			else
				$path = str_replace('_', DIRECTORY_SEPARATOR, $class) . '.php';
			include $path;
		});
	}

	// Other features

	/*
	Logic:
		We are supporting both styles of Path_Class and Path\Class, but to verify that the directory exists, we need to
		scan all directories in include paths

		$subpath = ^^
		$paths = explode(PATH_SEPARATOR, get_include_path());
		foreach($paths as $path) {
			
		}
	*/
}

// Dirty hack but thats OK, since require 'Pig/Autoloader' is a must in here,
// this comes in automagically!
function debug() {
	// Do we debug?
	if(defined('PIG_ENVIRONMENT') && PIG_ENVIRONMENT != 'devel')
		return;
	if(defined('PIG_DEBUG') && PIG_DEBUG != true)
		return;
	
	// Initialize session key
	if(PHP_SAPI != 'cli' && !isset($_SESSION['_debug_']))
		$_SESSION['_debug_'] = '';

	$args = func_get_args();
	foreach($args as $arg) {
		if(is_object($arg) || is_array($arg))
			$arg = print_r($arg, true);
		if(PHP_SAPI === 'cli')
			echo "{$arg}\n";
		else
			$_SESSION['_debug_'] .= "<pre>{$arg}</pre>\n";
	}
}