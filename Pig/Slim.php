<?php

// PHP 5.4.0 required
/*
	TODO: Port to 5.3.0!
	Closures cant access private members nor methods,
	they do not recognize $this or self because they are not class aware!
*/

class Pig_Slim {
	private $app = null;

	public function __construct($app) {
		$this->app = $app;
	}

	public function get($route, $classname, $method='get') {
		$this->app->get($route, function() use ($classname, $method) {
			$obj = new $classname($this->app);
			self::handleMethodCall($obj, $method, func_num_args(), func_get_args());
		});
	}

	public function post($route, $classname, $method='post') {
		$this->app->post($route, function() use ($classname, $method) {
			$obj = new $classname($this->app);
			self::handleMethodCall($obj, $method, func_num_args(), func_get_args());
		});
	}

	public function put($route, $classname, $method='put') {
		$this->app->put($route, function() use ($classname, $method) {
			$obj = new $classname($this->app);
			self::handleMethodCall($obj, $method, func_num_args(), func_get_args());
		});
	}

	public function delete($route, $classname, $method='delete') {
		$this->app->delete($route, function() use ($classname, $method) {
			$obj = new $classname($this->app);
			self::handleMethodCall($obj, $method, func_num_args(), func_get_args());
		});
	}

	/*
	 *		PRIVATE METHODS
	 */
	static private function handleMethodCall($obj, $method, $numargs, $args) {
		assert($numargs == count($args));
		$classname = get_class($obj);
		switch($numargs) {
			case 0: $obj->$method(); break;
			case 1: $obj->$method($args[0]); break;
			case 2: $obj->$method($args[0], $args[1]); break;
			case 3: $obj->$method($args[0], $args[1], $args[2]); break;
			case 4: $obj->$method($args[0], $args[1], $args[2], $args[3]); break;
			default: debug("Warning: Unsupported number of function arguments for {$classname}->{$method}");
		}
	}
}