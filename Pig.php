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

/*
TODO: implement functions for this (ID hashtag generation)

// Generate hash-like id method

// Initial id
$id = mt_rand();
echo "Initial id: {$id}\n";

$org_id = $id;

// TODO: Add some kind of simple 2-byte hash checksum here!

// Turn to string and pad it
$id = str_pad($id, 10, '0', STR_PAD_LEFT);	// Max integer in php is 10 chars
echo "Padded id: {$id}\n";

// Calculate the checksum from the padded id
$cs = str_pad(substr(hash('fnv132', $id), -2), 2, '0', STR_PAD_LEFT);
echo "Checksum: {$cs}\n";

$org_cs = $cs;

// In here we could mangle the string form..
$id = substr($id, 3) . substr($id, 0, 3);
echo "Mangled id: {$id}\n";

// Base conversion
$id = base_convert($id, 10, 34);
echo "Base converted id: {$id}\n";

// Add the checksum
// $id = $id . $cs;
// echo "With checksum: {$id}\n";

// rot13 it for lolz -- cant do this because we mess up 1,l,y and z characters!
//$id = str_rot13($id);
//echo "Rotated id: {$id}\n\n";

// Convert 1 (one) to y and l (small L) to z
$id = strtr($id, array('1'=>'y', 'l'=>'z'));
echo "Public id: {$id}\n\n";

//
// Convert back
echo "Converting it back\n";

// Convert y,z to 1,l
$id = strtr($id, array('y'=>'1', 'z'=>'l'));
echo "Fixed id: {$id}\n";

// rot13 it for lolz -- cant do this because we mess up 1,l,y and z characters!
// $id = str_rot13($id);
// echo "Rotated id: {$id}\n";

// Grab the checksum
// $cs = substr($id, -2);
// $id = substr($id, 0, -2);
// echo "Checksum: {$cs}\n";
// echo "Id without checksum: {$id}\n";

// Base conversion
$id = base_convert($id, 34, 10);
echo "Base converted id: {$id}\n";

// Turn to string and pad it
$id = str_pad($id, 10, '0', STR_PAD_LEFT);	// Max integer in php is 10 chars
echo "Padded id: {$id}\n";

// In here we could mangle the string form..
$id = substr($id, 7) . substr($id, 0, 7);
echo "Umangled id: {$id}\n";

// Trim padded 0 characters
$id = ltrim($id, '0');
echo "Trimmed id: {$id}\n";

// Calculate the checksum from the padded and unmangled id
$cs2 = str_pad(substr(hash('fnv132', $id), -2), 2, '0', STR_PAD_LEFT);
echo "Calculated checksum: {$cs2}\n";

echo "Final converted id: {$id} (vs {$org_id})\n";
echo "Ids " . (($id == $org_id) ? "Matches\n" : "Doesnt match\n");
// echo "Checksums " . (($cs == $org_cs && $cs == $cs2) ? "Matches\n" : "Doesnt match\n");
*/