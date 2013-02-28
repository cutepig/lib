<?php

class Pig_Pig {
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

	public static function hash($id) {
		// TODO: ROT13 for lolzors, problem is that we are messing 1->y and l->z, figure that out!
		// TODO: Add 2-char checksum to the end
		$id = str_pad($id, 10, '0', STR_PAD_LEFT);	// Pad left with zeros to fill 10 characters
		$cs = str_pad(substr(hash('fnv132', $id), -2), 2, '0', STR_PAD_LEFT);	// Generate 2-char checksum, TODO: utilize this!
		$id = substr($id, 3) . substr($id, 0, 3);	// Turn 3 first numbers as last
		$id = base_convert($id, 10, 34);			// Convert from base-10 to base-34
		$id = $id . $cs;							// Add checksum to the end
		$id = strtr($id, array('1'=>'y', 'l'=>'z'));	// Change 1's to y's and lowercase l's to z's
		return $id;
	}

	public static function unhash($id) {
		$id = strtr($id, array('y'=>'1', 'z'=>'l'));	// Change y's to 1's and z's to lowercase l's
		$oc = substr($id, -2);							// Grab the original checksum
		$id = substr($id, 0, -2);						// Remove checksum from id
		$id = base_convert($id, 34, 10);				// Convert from base-34 to base-10
		$id = str_pad($id, 10, '0', STR_PAD_LEFT);		// Left pad with zeros to fill 10 characters
		$id = substr($id, 7) . substr($id, 0, 7);		// Turn 3 last numbers as first
		$cs = str_pad(substr(hash('fnv132', $id), -2), 2, '0', STR_PAD_LEFT);	// Grab the checksum
		$id = ltrim($id, '0');
		return ($cs == $oc ? $id : false);
	}

	public static function signature($method, $url, $params, $secret, $alg='sha1') {
		$base = urlencode($method) . '&' . urlencode($url) . '&';
		// Order the array by keys, PHP doesn't support multiple indices with same key
		$_params = array_slice($params, 0);
		ksort($_params);
		$len = count($_params);
		$count = 0;
		foreach($_params as $key => $value) {
			$base .= urlencode("{$key}={$value}");
			$count++;
			if($count < $len)
				$base .= urlencode('&');
		}
		// debug($base);
		return base64_encode(hash_hmac($alg, $base, $secret));
	}

    public static function PDOFromConfig() {
    	// Create PDO
		$driver = Pig_Config::get('db.driver', 'mysql');
		$hostname = Pig_Config::get('db.hostname', '127.0.0.1');
		$port = Pig_Config::get('db.port', 3306);
		$dbname = Pig_Config::get('db.database', '');
		$user = Pig_Config::get('db.username', 'root');
		$password = Pig_Config::get('db.password', '');

		// Head on to http://www.microsoft.com/en-us/download/details.aspx?id=20098 to download SQLSRV drivers!
		
		if($driver == "mysql")
			$dsn = "{$driver}:host={$hostname};port={$port};dbname={$dbname}";
		else if($driver == "sqlsrv")
			$dsn = "{$driver}:server={$hostname},{$port};Database={$dbname}";
		else
			throw new Exception('Pig currently supports only mysql and sqlsrv drivers');	// FIXME:

		// debug($dsn);
		try {
			if($driver == "mysql")
				return new PDO($dsn, $user, $password, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
			else if($driver == "sqlsrv")
				return new PDO($dsn, $user, $password, array(PDO::SQLSRV_ENCODING_UTF8 => 1));
		} catch(PDOException $e) {
			// FIXME: Don't be too verbose about this in production stage
			// FIXME: Send an error mail somehwere about this incident
			// $this->json(array('error'=>$e->getMessage()));
			// debug($e->getMessage());
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

	/*
		Usage of serializeToXml_DOM

		$dom = new DOMDocument('1.0', 'utf-8');
		$root = $dom->createElement('root');	// Or whatever you want

		Pig_Pig::serializeToXml_DOM($data, $root, 'root');

		$dom->appendChild($root);

		$xml = $dom->saveXML()

		//
		// OR WITH ADDITIONAL DATA

		$dom = new DOMDocument('1.0', 'utf-8');
		$root = $dom->createElement('root');	// Or whatever you want

		// Additional fields
		$header = $dom->createElement('header');
		Pig_Pig::serializeToXml_DOM($this->header, $header, 'header');
		$root->appendChild($header);

		$dom->appendChild($root);

		$xml = $dom->saveXML();

	*/
	// This version uses DOM api
	static function serializeToXml_DOM($object, $xml, $upperkey=null) {
		if(is_array($object)) {
			foreach($object as $value) {
				$elem = $xml->ownerDocument->createElement($upperkey);
				if(is_string($value) || is_numeric($value))	// i.e. simple element
					$elem->appendChild($xml->ownerDocument->createTextNode($value));
				else
					self::serializeToXml_DOM($value, $elem, $upperkey);
				$xml->appendChild($elem);
			}
		}
		else if(is_object($object)) {
			foreach($object as $key => $value) {
				if(is_array($value)) { // Dont create a child-node explicitly
					self::serializeToXml_DOM($value, $xml, $key);
				}
				else {
					$elem = $xml->ownerDocument->createElement($key);
					if(is_string($value) || is_numeric($value))	// i.e. simple element
						$elem->appendChild($xml->ownerDocument->createTextNode($value));
					else
						self::serializeToXml_DOM($value, $elem, $key);
					$xml->appendChild($elem);
				}
			}
		}
		else if(is_string($object) || is_numeric($object)) {
			$xml->appendChild($xml->ownerDocument->createTextNode($object));
		}
	}

	/**
	 * Indents a flat JSON string to make it more human-readable.
	 *
	 * @param string $json The original JSON string to process.
	 *
	 * @return string Indented version of the original JSON string.
	 */
	static protected function indentJSON($json) {

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
}