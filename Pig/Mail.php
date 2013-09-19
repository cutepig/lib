<?php

class Pig_Mail {
	protected $smtp;
	protected $port;
	protected $batchMode;
	protected $debug;
	protected $hostname;
	protected $socket;
	protected $extensions;
	protected $auths;
	protected $headers;
	protected $contents;
	protected $attachments;
	protected $credentials;
	
	public function __construct($smtp, $port = false, $credentials = false, $batchMode = false, $debug = false) {
		$this->setSMTP($smtp);
		$this->setPort($port);
		$this->credentials = $credentials;
		$this->debug = $debug;

		$this->hostname = gethostname();	// TODO: Conf
		$this->socket = false;
		$this->extensions = false;
		$this->auths = false;

		$this->headers = false;
		$this->contents = false;
		$this->attachments = false;
	}

	public function __destruct() {
		if($this->socket !== false)
			$this->closeConnection();
	}

	public function setSMTP($smtp) {
		if($smtp !== false)
			$this->smtp = gethostbyname($smtp);
		else
			$this->smtp = $smtp;
	}

	public function setPort($port) {
		if($port !== false)
			$this->port = $port;
		else
			$this->port = getservbyname('smtp', 'tcp');
	}

	public function send($headers, $contents, $attachments = false) {
		if($this->socket === false)
			$this->initConnection();

		// Do stuff
		$this->headers = $headers;
		$this->contents = $contents;
		$this->attachments = $attachments;

		if($this->debug) print_r($this);

		$this->startMail();
		$this->sendContents();
		
		if(!$this->batchMode)
			$this->closeConnection();
	}

	protected function transact($m = false) {
		if($m !== false) {
			// Consume all of output stream
			$i = 0;
			$l = strlen($m);
			$r = fwrite($this->socket, $m);
			while($r && $i < $l) {
				$i += $r;
				$r = fwrite($this->socket, substr($m, $i));
			}
			if($r === false)
				throw new Exception("Failed to fwrite $m");
		}

		// Consume all of input stream
		$r = fread($this->socket, 4096);
		$ret = $r;
		while(!empty($r) && strlen($r) === 4096) {
			$ret .= $r;
			$r = fread($this->socket, 4096);
		}

		if($r === false)
			throw new Exception("Failed to fread after $m");

		return $ret;
	}

	protected function parseExtensions($r) {
		$lines = explode("\r\n", $r);
		if($this->debug) print_r($lines);

		// clear old extensions
		$this->extensions = false;
		// skip the welcome message
		$lines = array_slice($lines, 1);
		foreach($lines as $line) {
			$tokens = explode(" ", substr($line, 4));	// omit the "250 " or "250-"
			if(count($tokens) === 0 || empty($tokens[0]))
				continue;	// TODO: Warn?

			// First token is the extension name
			$this->extensions[] = $tokens[0];

			// Special internal handling
			if($tokens[0] === 'AUTH')
				$this->auths = array_slice($tokens, 1);
		}

		if($this->debug) print_r(array('Extensions' => $this->extensions, 'Auths' => $this->auths));
	}

	protected function sayHello() {
		// FIXME: Revert to HELO if EHLO is not supported (returns 502?)
		// Say hello
		$m = "EHLO {$this->hostname}\r\n";		// TODO: conf
		$r = $this->transact($m);
		if(strpos($r, '250') === false)
			throw new Exception("Failed:\n$m$r");
		if($this->debug) echo "$m$r";

		// Read extensions
		$this->parseExtensions($r);
	}

	protected function enableTLS() {
		// Start TLS
		$m = "STARTTLS\r\n";
		$r = $this->transact($m);
		if(strpos($r, '220') === false)
			throw new Exception("Failed\n$m$r");
		if($this->debug) echo "$m$r";

		if(file_exists('cacert.pem')) {	// TODO: Conf location
			stream_context_set_option($this->socket, 'ssl', 'verify_peer', true);
			stream_context_set_option($this->socket, 'ssl', 'cafile', 'cacert.pem');
		}
		$r = stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
		if($r === false)
			throw new Exception('Failed to enable TLS');

		$this->sayHello();
	}

	protected function authPlain() {
		$m = "AUTH PLAIN\r\n";
		$r = $this->transact($m);
		if(strpos($r, '334') === false)
			throw new Exception("Failed:\n$m$r");
		if($this->debug) echo "$m$r";

		// http://tools.ietf.org/html/rfc4616
		$authzid = '';
		$authcid = $this->credentials['username'];
		$passwd = $this->credentials['password'];
		$m = base64_encode("{$authzid}\0{$authcid}\0{$passwd}") . "\r\n";
		$r = $this->transact($m);
		if(strpos($r, '235') === false)
			throw new Exception("Failed:\n$m$r");
		if($this->debug) echo "$m$r";
	}

	protected function authLogin() {
		$m = "AUTH LOGIN\r\n";
		$r = $this->transact($m);
		if(strpos($r, '334') === false)
			throw new Exception("Failed:\n$m$r");
		if($this->debug) echo "$m$r";

		$authcid = $this->credentials['username'];
		$passwd = $this->credentials['password'];

		$m = base64_encode($authcid) . "\r\n";
		$r = $this->transact($m);
		if(strpos($r, '334') === false)
			throw new Exception("Failed:\n$m$r");
		if($this->debug) echo "$m$r";

		$m = base64_encode($passwd) . "\r\n";
		$r = $this->transact($m);
		if(strpos($r, '235') === false)
			throw new Exception("Failed:\n$m$r");
		if($this->debug) echo "$m$r";
	}

	protected function authCramMD5() {
		// FIXME: Experimental WIP, untested!
		$m = "AUTH CRAM-MD5\r\n";
		$r = $this->transact($m);
		if(strpos($r, '334') === false)
			throw new Exception("Failed:\n$m$r");
		if($this->debug) echo "$m$r";

		// This is tricky here
		if(!preg_match_all("/^334 ([\w=]+)/", $r, $m))
			throw new Exception("Failed to find HMAC key from $r");
		if($this->debug) print_r($m);

		// ..
	}

	protected function initConnection() {
		// open up the connection
		$s = stream_socket_client("tcp://{$this->smtp}:{$this->port}", $errno, $errstr, 30);
		$this->socket = $s;

		// Read the initial response
		$r = $this->transact();
		if(strpos($r, '220') === false)
			throw new Exception("Failed:\n(Connect)\n$r");
		if($this->debug) echo "$r";

		// Say hello
		$this->sayHello();

		// Check TLS
		if(in_array('STARTTLS', $this->extensions))
			$this->enableTLS();

		// Fail if credentials are given, but there is no AUTH
		if($this->credentials !== false && !in_array('AUTH', $this->extensions))
			throw new Exception('Credentials where passed but AUTH is not supported');

		// FIXME: Really fail here? credentials not given, but AUTH is supported
		// if($this->credentials === false && in_array('AUTH', $this->extensions))
		//	throw new Exception('Credentials not given');

		// Do auth
		if($this->credentials !== false && in_array('AUTH', $this->extensions)) {
			// Choose the algorithm
			if(in_array('PLAIN', $this->auths))
				$this->authPlain();
			else if(in_array('LOGIN', $this->auths))
				$this->authDigest();
		}

		// Ready to start mailing!
	}

	protected function closeConnection() {
		// Quit
		$m = "QUIT\r\n";
		$r = $this->transact($m);
		if(strpos($r, '221') === false)
			throw new Exception("Failed:\n$m$r");
		if($this->debug) echo "$m$r";
		fclose($this->socket);
		$this->socket = false;
	}

	protected function startMail() {
		$from = $this->headers['From'];
		$to = $this->headers['To'];

		// Validating funcitonality from mail3::send2, up until socket opening
		// TODO: Also validate that these actually exist
		if(filter_var($from, FILTER_VALIDATE_EMAIL) === false)
			throw new Exception('Invalid from address');
		if(filter_var($to, FILTER_VALIDATE_EMAIL) === false)
			throw new Exception('Invalid recipient address');
		
		// We allow contents to be defined as plain content, so convert it to
		// array to unify the handling
		if(!is_array($this->contents)) {
			// TODO: Further checking, like match <!DOCTYPE or <html
			$type = $this->contents[0] === '<' ? 'html' : 'text';
			$this->contents = array($type => $this->contents);
		}

		// TODO: Validate message .. afternote: how?
		foreach($this->contents as $key => $val) {
			// Replace pure \n characters with \r\n
			$this->contents[$key] = preg_replace("/([^\r]?)\n/", "$1\r\n", $val);
			// Replace lines starting with . with ..
			$this->contents[$key] = preg_replace("/\r\n\./", "\r\n..", $val);
		}

		// From who
		$m = "MAIL FROM:<{$from}>\r\n";
		$r = $this->transact($m);
		if(strpos($r, '250') === false)
			throw new Exception("Failed:\n$m$r");
		if($this->debug) echo "$m$r";

		// To whom
		$m = "RCPT TO:<{$to}>\r\n";
		$r = $this->transact($m);
		if(strpos($r, '250') === false)
			throw new Exception("Failed:\n$m$r");
		if($this->debug) echo "$m$r";
	}

	protected function sendContents() {
		// ch : Note that according to specs, messages should be word wrapped around 7x characters or so.
		// See this function, and then theres utf-8 version in the comments.
		// http://www.php.net/manual/en/function.wordwrap.php

		// TODO: Reply-to fields etc..
		$from = $this->headers['From'];
		$to = $this->headers['To'];
		$subject = $this->headers['Subject'];
		// FIXME: Include attachments to the equation
		$boundary = (count($this->contents) > 1) ? mt_rand(1000000000, 1999999999) : false;

		// Data command
		$m = "DATA\r\n";
		$r = $this->transact($m);
		if(strpos($r, '354') === false)
			throw new Exception("Failed:\n$m$r");
		if($this->debug) echo "$m$r";

		// Message itself
		$m = "From: \"{$from}\" <{$from}>\r\n";	// FIXME: from name
		$m .= "To: \"{$to}\" <{$to}>\r\n";		// FIXME: to name
		// $m .= "Cc: {$cc}\r\n";
		$m .= "Date: " . date('r') . "\r\n";
		$m .= "Subject: $subject\r\n";	// FIXME: $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
		$m .= "MIME-Version: 1.0\r\n";

		if($boundary !== false) {
			$m .= "Content-Type: multipart/alternative;\r\n  boundary=boundary-type-{$boundary}-alt\r\n";
			$m .= "\r\n--boundary-type-{$boundary}-alt\r\n";
		}

		// Contents
		foreach($this->contents as $type => $content) {
			// Plain text
			if($type === 'text')
				$m .= "Content-Type: text/plain; charset=\"utf-8\"\r\n";
			else if($type === 'html')
				$m .= "Content-Type: text/html; charset=\"utf-8\"\r\n";

			// FIXME:
			// As stated in the definition of the Content-Transfer-Encoding field,
			// no encoding other than "7bit", "8bit", or "binary" is permitted for entities of type "multipart".
			// $m .= "Content-Transfer-Encoding: quoted-printable\r\n";
			$m .= "\r\n{$content}\r\n";

			if($boundary !== false)
				$m .= "\r\n--boundary-type-{$boundary}-alt\r\n";
		}

		//
		// Attachments -- FIXME: for each attachment
		// $m .= "Content-Transfer-Encoding: base64\r\n";
		// $m .= "Content-Type: text/plain; name=\"Here2.txt\"\r\n";
		// $m .= "Content-Disposition: attachment; filename=\"Here2.txt\"\r\n";
		// $m .= "\r\n{$data}\r\n";	// Data should be base64 encoded, with some special format
		// $m .= "\r\n--boundary-type-{$boundary}-alt\r\n";
		
		// End
		$m .= "\r\n.\r\n";

		$r = $this->transact($m);
		if(strpos($r, '250') === false)
			throw new Exception("Failed:\n$m$r");
		if($this->debug) echo "$m$r";
	}
}