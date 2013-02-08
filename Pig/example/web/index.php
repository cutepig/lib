<?php
/*
 *	Project
 *
 *	Description: Example for Pig+Slim+Twig environment
 */

//
// Environment bootstrap
$ini = parse_ini_file('../environment.ini');
$environment = $ini && isset($ini['environment']) ? $ini['environment'] : 'devel';
$debug = $ini && isset($ini['debug']) ? ($ini['debug'] != '0') : false;

//
// Setup
if(!defined('PIG_ENVIRONMENT'))
	define('PIG_ENVIRONMENT', $environment);	// devel, qa or prod
if(!defined('PIG_DEBUG'))
	define('PIG_DEBUG', $debug);

//
// PHP Error reporting
if(PIG_ENVIRONMENT == 'devel' && PIG_DEBUG == true) {
	ini_set('error_reporting', E_ALL);
	ini_set('display_errors', 1);
}

// Paths
set_include_path(implode(PATH_SEPARATOR, array(
	get_include_path(),
	'../handlers',
	'../lib',		// local kernel
	'../../../lib'	// zwamp/vdrive/lib FIXME:
)));

// Start services
session_start();

// Autoloader
require 'Pig/Autoloader.php';
Pig_Autoloader::init();

// Config test
Pig_Config::init('../config.ini', PIG_ENVIRONMENT);

//
// Slim setup
$app = new Slim\Slim(array(
	'debug' => Pig_Config::get('slim.debug', PIG_ENVIRONMENT == 'devel'),
	'mode' => Pig_Config::get('slim.mode', PIG_ENVIRONMENT),
	// Uncomment view property to disable Twig
	'view' => new Pig_Slim_TwigView('../views/', array(
		'debug' => Pig_Config::get('twig.debug', PIG_ENVIRONMENT == 'devel'),
		// 'cache' => Pig_Config::get('twig.cachedir', ../views/cache')
	))
));
$app->setName(Pig_Config::get('app.name', 'Pig example'));

//
// Routing helper
$papp = new Pig_Slim_Autorouter($app);

// Launch the application
$app->run();