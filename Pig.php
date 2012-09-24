<?php

class Pig {
	// Misc utilities
	public static function collect($collection, $property) {
        $values = array();
        if(count($collection) && is_object($collection[0])) {
	        foreach ($collection as $item) {
	            $values[] = $item->{$property};
	        }
	    }
	    else if(count($collection) && is_array($collection[0])) {
			foreach ($collection as $item) {
	            $values[] = $item[$property];
	        }
	    }
        return $values;
    }

    public static function getParam($param, $default) {
    	if(isset($_REQUEST[$param]) && $_REQUEST[$param])
    		return $_REQUEST[$param];
    	return $default;
	}

	public static function session($name, $value=null) {
		if($value === null) {
			if(isset($_SESSION[$name]))
				return $_SESSION[$name];
			return '';
		}
		$_SESSION[$name] = $value;
		return $value;
	}

    public static function PDOFromConfig() {
    	// Create PDO
		// dsn = "mysql:host={$hostname}:port={$port}:dbname={$dbname}";
		$hostname = Pig_Config::get('db.hostname', '127.0.0.1');
		$port = Pig_Config::get('db.port', 3306);
		$dbname = Pig_Config::get('db.database', '');
		$user = Pig_Config::get('db.user', 'root');
		$password = Pig_Config::get('db.password', '');

		$dsn = "mysql:host={$hostname};port={$port};dbname={$dbname}";
		debug($dsn);
		try {
			return new PDO($dsn, $user, $password, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
		} catch(PDOException $e) {
			// FIXME: Don't be too verbose about this in production stage
			// FIXME: Send an error mail somehwere about this incident
			// $this->json(array('error'=>$e->getMessage()));
			debug($e->getMessage());
			// return null;
			throw $e;	// FIXME:
		}
    }

    // Helper to bind values of an object to pdo statement
	static function bindParams($stmt, $obj) {
		foreach($obj as $key => $value) {
			$stmt->bindValue(":$key", $value);
		}
	}
}