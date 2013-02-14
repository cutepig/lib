<?php

class Pig_Slim_BaseHandler {
	protected $pdo;
	protected $doPdo;
	protected $app;
	protected $request;
	protected $response;
	protected $view;

	// protected $config;

	public function __construct($app, $doPdo=true) {
		//
		// Slim
		$this->app = $app;
		$this->request = $app->request();
		$this->response = $app->response();
		
		//
		// Twig
		$this->view = new stdClass;
		$this->view->common = new stdClass;
		$this->view->common->rootUri = $this->request->getUrl() . $this->request->getRootUri();
		$this->view->common->pageUri = $this->request->getResourceUri();
		$this->view->actions = new stdClass;

		//
		// PDO
		$this->doPdo = $doPdo;
		if($doPdo) {
			$this->pdo = Pig_Pig::PDOFromConfig();
			if(!$this->pdo)
				return;
		}

		// ARGH do some stuff to test the connection
		/*
		$stmt = $this->pdo->prepare('show table status from pigstats like :table');
		$stmt->execute(array(':table'=>'Statistics'));
		debug($stmt->fetchAll());
		*/

		//
		// Child
		$this->init();
	}

	/**
	 * Indents a flat JSON string to make it more human-readable.
	 *
	 * @param string $json The original JSON string to process.
	 *
	 * @return string Indented version of the original JSON string.
	 */
	static protected function indent($json) {

	    $result      = '';
	    $pos         = 0;
	    $strLen      = strlen($json);
	    $indentStr   = '  ';
	    $newLine     = "\n";
	    $prevChar    = '';
	    $outOfQuotes = true;

	    for ($i=0; $i<=$strLen; $i++) {

	        // Grab the next character in the string.
	        $char = substr($json, $i, 1);

	        // Are we inside a quoted string?
	        if ($char == '"' && $prevChar != '\\') {
	            $outOfQuotes = !$outOfQuotes;
	        
	        // If this character is the end of an element, 
	        // output a new line and indent the next line.
	        } else if(($char == '}' || $char == ']') && $outOfQuotes) {
	            $result .= $newLine;
	            $pos --;
	            for ($j=0; $j<$pos; $j++) {
	                $result .= $indentStr;
	            }
	        }
	        
	        // Add the character to the result string.
	        $result .= $char;

	        // If the last character was the beginning of an element, 
	        // output a new line and indent the next line.
	        if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
	            $result .= $newLine;
	            if ($char == '{' || $char == '[') {
	                $pos ++;
	            }
	            
	            for ($j = 0; $j < $pos; $j++) {
	                $result .= $indentStr;
	            }
	        }
	        
	        $prevChar = $char;
	    }

	    return $result;
	}

	/*
	 *		PUBLIC / PROTECTED FUNCTIONS
	 */

	protected function valid() {
		return $doPdo ? ($this->pdo != null) : true;
	}

	// Implement custom initialization with this function in derived handler
	protected function init() {}

	// Helper function to emit JSON from object along with the proper headers
	protected function json($object) {
		// Push the header out
		$response = $this->app->response();
		// $response['Content-Type'] = 'application/json';
		$response->write('<pre>');
		$response->write(self::indent(json_encode($object/*, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK*/)));
		$response->write('</pre>');
	}

	// Helper function to parse the received json to stdClass
	protected function parse($json) {
		return json_decode($json);
	}

	protected function render($template) {
		$this->app->render($template, get_object_vars($this->view));
	}

	// Helper redirect function that redirects to this webapps resource
	protected function redirect($resource) {
		$this->response->redirect($this->request->getRootUri() . $resource);
	}

	protected function getFullResourceUri() {
		return $this->request->getRootUri() . $this->request->getResourceUri();
	}
}