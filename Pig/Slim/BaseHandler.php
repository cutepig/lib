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
		$response->write(Pig_Pig::indentJSON(json_encode($object/*, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK*/)));
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