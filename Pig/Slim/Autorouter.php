<?php

/*
	Pig_Slim_Autorouter

	Constructor:
		1st argument - Slim application
		2nd argument - Path to handlers that are named like the containing class. If this is left out or declared false or null,
						autoloading is expected and the computed .php files aren't explicitly included.

	How controllers, actions and parameters are mapped to handlers:
		URL's are of the form:
			/
			/controller
			/controller/action
			/controller/parameter
			/controller/action/parameter

		For aestethic reason any of these can end with / forwardslash.

		Controllers are mapped to a class named <Controller>Handler which is looked in <handlers_path>/<Controller>Handler.php.
		Note the capitalization of the controller class.

		Controller class constructor has to take the Slim application as an argument, best thing would be to inherit from Pig_Slim_BaseHandler
		and not to override the constructor.
		
		Actions are mapped to a method of the controller class, first trying method with explicit HTTP Method and then without.
		For example GET /foo/bar searches getBar() or bar() function from class FooHandler.
		Note the capitalization of the action in the explicit method form.

		If neither is found then index-handler is called with 'bar' as a parameter for that method,
		first getIndex('bar') and then index('bar').

		If neither of those is found then '404 Not Found' error is raised.
		Note that there might be name collision clash when the parameter in URL of the form /controller/parameter 
		clashes with an action method!

		Actions that take the parameter value should still use a default value for that parameter or else with an URL without the parameter
		the call will fail and generate an error (FIXME!)

		If controller or action contains a hyphen, it is stripped and the following letter is capitalized.
		For example /foo-bar/ is mapped to FooBarHandler, GET /foo/bar-dude/ is mapped to FooHandler::getBarDude or FooHandler::barDude.

		Root URL / is mapped to IndexHandler and its index action is called. (TODO: Allow configuration for the default handler!)

	December 2013, v1.1:
		Ugly porting of v2.0 autorouter to this version. 2.0 doesn't use Slim at all, so we had to fix it to utilize Slim and also
		be fully compatible with the old v1.0 version. It now supports hierarchical handlers, folders within handlers directory.
		Only thing is that IndexHandler isn't checked on these subdirectories, but rather make a xxxHandler to the parent folder
		where the subdirectory xxx lies. For e.g.

		/
		/IndexHandler.php
		/AdminHandler.php 	<-- Not /Admin/IndexHandler.php
		/Admin
		/Admin/UserHandler.php

		Also unlike v2.0 we still support only 1 parameter for backwards compatibility. Rest of the parameters are simply just discarded.
		The handler registered to Slim supports currently 8 parts on the resource uri, so you can make up to 5 levels or directories,
		controller, method and parameter, or without method or parameter up to 7 levels of directories and controller (Remember that
		IndexHandler isn't checked at all in subdirectories, this is the behaviour of v2.0 anyway and doesn't require backwards
		compatibility to v1.0).
*/

class Pig_Slim_Autorouter {
	protected $app;
	protected $handlersDir;
	protected $namespace;

	public function __construct($app, $handlersDir=false, $namespace=false) {
		if($handlersDir === false)
			$handlersDir = '..' . DIRECTORY_SEPARATOR . 'handlers';	// Default

		$this->app = $app;
		$this->handlersDir = realpath($handlersDir);
		$this->namespace = $namespace !== false ? $namespace : '';

		$self = $this;
		$app->map('/(:foobar+)', function($foobar = false) use($app, $self) {
			$self->callRequestedHandler();
		})->via('GET', 'POST', 'PUT', 'DELETE');

/*
		// For aesthetic reasons, allow /foo/, /foo/bar/ and /foo/bar/123/
		$app->map('/(:a(/:b(/:c(/:d(/:e(/:f(/:g(/:h/))))))))', function(
			$a=null, $b=null, $c=null, $d=null, $e=null, $f=null, $g=null, $h=null) use($app, $self) {
			$self->callRequestedHandler();
		})->via('GET', 'POST', 'PUT', 'DELETE');
*/
	}

	public function tryHandler($cls, $method, $params) {
		$classPath = $this->handlersDir . DIRECTORY_SEPARATOR . str_replace('_', DIRECTORY_SEPARATOR, $cls) . '.php';
		debug(array(
			'Class' => $cls,
			'Method' => $method,
			'Params' => $params,
			'Path' => $classPath
		));
		
		if(file_exists($classPath)) {
			require_once($classPath);
			if(class_exists($cls) && method_exists($cls, $method)) {
				$o = new $cls($this->app);
				debug("Calling $cls::$method");
				if(!count($params))
					call_user_func(array($o, $method));
				else
					call_user_func(array($o, $method), $params[0]);
				debug('OK');

				// Emit debug after the handler has processed its output
				if(isset($_SESSION['_debug_'])) {
					echo $_SESSION['_debug_'];
					unset($_SESSION['_debug_']);
				}
				return true;
			}
		}

		debug('NOPE');
		return false;
	}

	public function callRequestedHandler() {
		$resource = $this->app->request()->getResourceUri();
		// $left = explode('/', preg_replace('/^\//', '', $resource));
		$left = explode('/', trim($resource, '/'));
		$right = array();

		// Trim empty items
		if(count($left) && $left[0] === '')
			array_shift($left);
		
		// Support SOAP calls as in v1.0 where you simply ignore the parts that follow after #
		$soapTest = true;
		$left = array_filter($left, function($x) use($soapTest) {
			return ($soapTest = ($soapTest && !preg_match('/^#/', $x)));
		});
		debug($left);
		
		$self = __CLASS__;
		while(count($left) >= 0) {
			debug(array(
				'Left' => $left,
				'Right' => $right
			));

			// Form the classname
			if(count($left)) {
				$cls = implode('_', array_map(function($x) use($self) {
					return $self::camelCasePart($x, true);
				}, $left)) . 'Handler';
			}
			else 
 				$cls = 'IndexHandler';

 			if($this->namespace)
 				$cls = $this->namespace . '_' . $cls;

 			// Method and parameters
 			$method = (count($right) && $right[0] !== '' ? $right[0] : 'index');
			$params = array_slice($right, 1);
 			$httpMethod = strtolower($this->app->request()->getMethod());

 			// FIXME: Move these 2 blocks to separate function
 			// Try method as a method name first
 			if($method !== 'index') {
 				// Explicit method
 				if($this->tryHandler($cls, $httpMethod . self::camelCasePart($method, true), $params))
 					return true;
 				// Non-explicit method
 				if($this->tryHandler($cls, self::camelCasePart($method, false), $params))
 					return true;
 			}
			
			// Use index as method, move method to parameters
			if($method !== 'index')
				array_unshift($params, $method);
			// Explicit method
			if($this->tryHandler($cls, $httpMethod . 'Index', $params))
				return true;
			// Non-explicit method
			if($this->tryHandler($cls, 'index', $params))
				return true;
			
			if(!count($left))
				break;
			
			array_unshift($right, array_pop($left));
		}

		debug("Failed to find handler for $resource");
		
		if(isset($_SESSION['_debug_'])) {
			echo $_SESSION['_debug_'];
			unset($_SESSION['_debug_']);
		}
		// $this->app->notFound();
		return false;
	}


	// Requires the directory of handlers as parameter
	// Returns list of strings of form "METHOD url"
	public static function getHandlers($handlersDir, $namespace = false) {
		$dir = $handlersDir . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $namespace);
		$files = self::getHandlersInner($dir);
		debug(array('readHandlersInner' => $files));

		// Fix the $dir out of these
		(substr($dir, -1) !== DIRECTORY_SEPARATOR) && ($dir .= DIRECTORY_SEPARATOR);

		$handlers = array();
		$len = strlen($dir);
		foreach($files as $file) {
			// Collect the relative path
			$f = substr($file, $len);

			// Remove the extension
			$f = preg_replace('/\.[^$]*/', '', $f);

			// Replace / with _ to make it into class name
			$ns = ($namespace !== false) ? $namespace . '_' : '';
			$cls = $ns . trim(str_replace(DIRECTORY_SEPARATOR, '_', $f), '_');
			echo "Including $file for class $cls\n";
			include_once($file);

			$methods = get_class_methods($cls);
			// Strip methods
			$_methods = array();
			foreach($methods as $method) {
				if($method[0] != '_' && $method !== $cls && $method[0] === strtolower($method[0])) {
					// FIXME: We aren't back-converting the capitalizations correctly in all cases
					if(preg_match('/get/', $method))
						$_methods[] = array('GET', self::unCamelCasePart(substr($method, 3)));
					else if(preg_match('/post/', $method))
						$_methods[] = array('POST', self::unCamelCasePart(substr($method, 4)));
					else if(preg_match('/put/', $method))
						$_methods[] = array('PUT', self::unCamelCasePart(substr($method, 3)));
					else if(preg_match('/delete/', $method))
						$_methods[] = array('DELETE', self::unCamelCasePart(substr($method, 6)));
					else
						$_methods[] = array('*', self::unCamelCasePart($method, false));
				}
			}
			$methods = $_methods;

			$urlFriendly = str_replace('_', '/', ltrim($cls, $ns));
			$handler = self::unCamelCasePart(preg_replace('/(Handler)$/', '', $urlFriendly));
			foreach($methods as $method) {
				if($method[1] == 'index')
					$method[1] = '';
				$handlers[] = "{$method[0]} /{$handler}/{$method[1]}";
			}
		}

		return $handlers;
	}

	protected static function getHandlersInner($dir) {
		// FIXME: strip the root here!
		$files = scandir($dir);
		$files2 = array();
		if($files !== false) {
			// Recurse
			foreach($files as $file) {
				if($file == '.' || $file == '..')
					continue;
				$path = $dir . DIRECTORY_SEPARATOR . $file;
				if(is_dir($path))
					$files2 = array_merge($files2, self::getHandlersInner($path));
				else {
					$pi = pathinfo($path);
					if($pi['extension'] === 'php')
						$files2[] = $path;	// FIXME: Convert to class name and confirm it exists
				}
			}
		}

		return $files2;
	}

	public static function debugListHandlers($pathToHandlers, $namespace = false) {
		$handlers = self::getHandlers($pathToHandlers, $namespace);
		foreach($handlers as $handler)
			debug($handler);
	}

	protected static function camelCasePart($part, $ucFirst) {
		// Strip the - markers from the beginning and end of the name (This is silly..)
		// $part = trim($part, '-');

		// Convert to worlds for ucwords and then capitalize
		$words = str_replace('-', ' ', strtolower($part));
		$capitalized = str_replace(' ', '', ucwords($words));
		if(!$ucFirst)
			return lcfirst($capitalized);
		return $capitalized;
	}

	protected static function unCamelCasePart($part) {
		// Convert the first character to lowercase so we dont create a name starting with -
		$part = lcfirst($part);
		return strtolower(preg_replace('/^\/([A-Z]+)/e', "'-'.'$1'", $part));
	}
}