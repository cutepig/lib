<?php

class Pig_Slim_TwigView extends \Slim\View
{
	public function __construct($path, $options) {
		$this->loader = new Twig_Loader_Filesystem($path);
		$this->twig = new Twig_Environment($this->loader, $options);
	}
	public function render($template) {
		// FIXME: check if we can have object as $this->data, then convert to array in here?
		echo $this->twig->render($template, $this->data);
	}

	public function getTwig() {
		return $this->twig;
	}
}

/*
// Register that view for Slim object
$app = new \Slim\Slim(array(
	'view' => new TwigView('/path/to/templates', array(
		'debug' => true,
		'cache' => '/path/to/template/cache'
	))
));

// Then normally handle the web call
$app->get('/foo', function() {
	$app->render('template.twig', array('foo'=>'bar'));
});
*/