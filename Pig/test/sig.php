<?php

include '../../Pig.php';
include '../Autoloader.php';

$url = 'http://foo.com/bar';
$timestamp = time();		// In real use we should sync and check the interval length
$nounce = md5(mt_rand());	// In real use we should track multiple uses of same nounce
$secret = md5(mt_rand());	// In real use, defined constant
$public = md5(mt_rand());	// In real use, defined constant
$params = array(
	'foo' => urlencode('bar'),
	'bar' => urlencode('foo'),
	'xxx' => urlencode('some value'),
	'timestamp' => $timestamp,
	'nounce' => $nounce
);

$sig = Pig::signature('GET', $url, $params, $secret);

echo "GET $url $timestamp $nounce $secret $sig\n";

