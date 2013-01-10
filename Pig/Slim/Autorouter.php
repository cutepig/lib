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
*/

class Pig_Slim_Autorouter {
	protected $app;
	protected $pathToHandlers;

	public function __construct($app, $pathToHandlers=false) {
		$this->app = $app;
		$this->pathToHandlers = $pathToHandlers;	// TODO: Normalize?

		// FIXME: This fails with /foo/ or /foo/bar/
		$app->get('/(:controller(/:action(/:parameter)))', array($this, 'requestHandler'));
		$app->post('/(:controller(/:action(/:parameter)))', array($this, 'requestHandler'));
		$app->put('/(:controller(/:action(/:parameter)))', array($this, 'requestHandler'));
		$app->delete('/(:controller(/:action(/:parameter)))', array($this, 'requestHandler'));

		// For aesthetic reasons, allow /foo/, /foo/bar/ and /foo/bar/123/
		$app->get('/:controller/(:action/(:parameter/))', array($this, 'requestHandler'));
		$app->post('/:controller/(:action/(:parameter/))', array($this, 'requestHandler'));
		$app->put('/:controller/(:action/(:parameter/))', array($this, 'requestHandler'));
		$app->delete('/:controller/(:action/(:parameter/))', array($this, 'requestHandler'));
	}

	public function requestHandler($controller=null, $action=null, $parameter=null) {
		$method = strtolower($this->app->request()->getMethod());

		if(is_null($controller) || preg_match("/^#/", $controller)) {	// handler for /
			$handler = 'IndexHandler';
			$functions = array("{$method}Index", 'index');
			$parameters = array(null, null);
		}
		else if(is_null($action) || preg_match("/^#/", $action)) {	// handler for /controller
			$handler = $this->capitalizeName($controller, true) . 'Handler';	// FIXME: capitalize
			$functions = array("{$method}Index", 'index');
			$parameters = array(null, null);
		}
		else if(is_null($parameter) || preg_match("/^#/", $parameter)) {	// handler for /controller/action or /controller/parameter
			$handler = $this->capitalizeName($controller, true) . 'Handler';
			$functions = array("{$method}{$this->capitalizeName($action, true)}", $this->capitalizeName($action, false), "{$method}Index", 'index');
			$parameters = array(null, null, $action, $action);
		}
		else {	// handler for /controller/action/parameter
			$handler = $this->capitalizeName($controller, true) . 'Handler';
			$functions = array("{$method}{$this->capitalizeName($action, true)}", $this->capitalizeName($action, false));
			$parameters = array($parameter, $parameter);
		}

		if(count($functions) != count($parameters))
			$this->app->notFound();
		
		// debug(array('handler'=>$handler,'functions'=>$functions,'parameters'=>$parameters));

		// FIXME: class_exists calls autoloader by default, omitting it would mean that we would handle the loading ourselves

		// Load the handler class or let autoloader take care of it
		$filename = "{$this->pathToHandlers}/{$handler}.php";
		if($this->pathToHandlers && !class_exists($handler) && file_exists($filename)) {
			// debug("Including $filename");
			include($filename);
		}

		// Use this method (no pun intended!) with in_array instead of method_exists which returns true on protected/private methods!
		$methods = get_class_methods($handler);
		
		for($i = 0; $i < count($functions); $i++) {
			$fn = $functions[$i];
			// HACKETY HACK HACK
			if($fn[0] == '_' || $fn == $handler)
				continue;

			if(in_array($fn, $methods)) {
				$obj = new $handler($this->app);
				if(is_null($parameters[$i]))
					call_user_func(array($obj, $fn));
				else
					call_user_func(array($obj, $fn), $parameters[$i]);

				// Emit debug after the handler has processed its output
				if(isset($_SESSION['_debug_'])) {
					echo $_SESSION['_debug_'];
					unset($_SESSION['_debug_']);
				}
				return;
			}
		}
		
		if(isset($_SESSION['_debug_'])) {
			echo $_SESSION['_debug_'];
			unset($_SESSION['_debug_']);
		}
		$this->app->notFound();
	}

	// Requires the directory of handlers as parameter
	public static function debugListHandlers($pathToHandlers) {
		$dir = opendir($pathToHandlers);
		while(($entry = readdir($dir)) != false) {
			$file = $pathToHandlers . '/' . $entry;
			// debug($file);
			if(is_file($file)) {
				$pathinfo = pathinfo($file);
				if($pathinfo['extension'] == 'php') {
					$cls = $pathinfo['filename'];
					if(!class_exists($cls))
						include($file);
					$methods = get_class_methods($cls);
					// Strip methods
					$_methods = array();
					foreach($methods as $method) {
						if($method[0] != '_' && $method != $cls && $method[0] == strtolower($method[0])) {
							// FIXME: We aren't back-converting the capitalizations correctly in all cases
							if(preg_match('/get/', $method))
								$_methods[] = array('GET', self::uncapitalizeName(substr($method, 3)));
							else if(preg_match('/post/', $method))
								$_methods[] = array('POST', self::uncapitalizeName(substr($method, 4)));
							else if(preg_match('/put/', $method))
								$_methods[] = array('PUT', self::uncapitalizeName(substr($method, 3)));
							else if(preg_match('/delete/', $method))
								$_methods[] = array('DELETE', self::uncapitalizeName(substr($method, 6)));
							else
								$_methods[] = array('*', self::uncapitalizeName($method, false));
						}
					}
					$methods = $_methods;

					$handler = self::uncapitalizeName(preg_replace('/(Handler)$/', '', $cls));
					foreach($methods as $method) {
						if($method[1] == 'index')
							$method[1] = '';
						debug("{$method[0]} /{$handler}/{$method[1]}");
					}
				}
			}
		}
	}

	// If isController is true, capitalize the first letter, else lowercase the first letter
	protected function capitalizeName($str, $isController) {
		// Strip the - markers from the beginning and end of the name (This is silly..)
		// $str = trim($str, '-');

		// Convert to worlds for ucwords and then capitalize
		$words = str_replace('-', ' ', strtolower($str));
		$capitalized = str_replace(' ', '', ucwords($words));
		if(!$isController)
			return lcfirst($capitalized);
		return $capitalized;

	}

	// Used for debugListHandlers
	protected static function uncapitalizeName($str) {
		// Convert the first character to lowercase so we dont create a name starting with -
		$str = lcfirst($str);
		return preg_replace('/([A-Z]+)/e', "'-'.strtolower('$1')", $str);
	}
}