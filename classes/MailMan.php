<?php

class MailMan {

	/**
	 * Interval in seconds to check newsletter records in database
	 *
	 * @var integer
	 */
	protected $loop_interval = 1;

	/**
	 * Interval for sending out emails to a group of recipients
	 *
	 * @var integer
	 */
	protected $batch_interval = 1;

	/**
	 * Flag shows if the interruption signal is received
	 * to be able to terminate process after the current iteration
	 *
	 * @var boolean
	 */
	protected $out = false;

	/**
	 * Is debug mode on or off
	 *
	 * @var boolean
	 */
	protected static $dbg = true;

	/**
	 * How many emails to send out in one batch
	 *
	 * @var int
	 */
	protected $batch_limit = 20;
	protected $cronlock;
	protected $sendmethod = 'sendInTO';
	protected $config_used = 'config.php';
	protected $confirm_reading = false;

	public function __construct() {
		// declare signal handlers
		if (extension_loaded('pcntl')) {
			declare(ticks = 1);

			pcntl_signal(SIGINT, array(&$this, 'interrupt'));
			pcntl_signal(SIGTERM, array(&$this, 'interrupt'));
			pcntl_signal(SIGUSR1, array(&$this, 'interrupt'));
		} else {
			self::$dbg = true;
			self::dbg('pcntl extension is not loaded');
		}

		register_shutdown_function(array(&$this, 'shutdown'));
		$this->cronlock = NLM_DIR . '/cron.lock';
	}

	/**
	 * Set error handler
	 *
	 */
	public function setErrorHandler() {
		set_error_handler(array(&$this, 'handle_errors'), E_ALL | E_STRICT);
	}

	/**
	 * Log all errors
	 *
	 * @param integer $errno
	 * @param string $errstr
	 * @param string $errfile
	 * @param integer $errline
	 * @param array $errcontext
	 */
	public function handle_errors($errno, $errstr, $errfile, $errline, $errcontext) {
		self::dbg('[' . $errno . ']\'' . $errstr . "' in " . $errfile . ':' . $errline);
	}

	/**
	 * Main function that checks if the new jobs have been added to the queue
	 * and if yes, then processes them
	 */
	public function run($as_deamon = false) {
		self::dbg('Starting up NewsletterMan Mailer... ');
		self::dbg('Configuration: ' . $this->config_used);
		// If not called to run as deamon just send newsletters once
		if ($as_deamon !== true) {
			return $this->cron();
		}

		// loop forever until terminated by SIGINT
		while (!$this->out) {
			try {
				// loop untill terminated but with taking some nap
				while (!$this->out && $this->rested()) {

					$newsletters = Newsletter::getScheduled();
					if (!$newsletters) {
						continue;
					}

					self::dbg(sprintf("Fetched %d newsletters", count($newsletters)));
					/* @var $newsletter Newsletter */
					foreach ($newsletters as $newsletter) {
						$newsletter->trigger();
						$this->sendNewsLetter($newsletter);
					}
				}
			} catch (PDOException $e) {
				// if connection disappeared - try to restore it
				$error_code = $e->getCode();
				if ($error_code != 1053 && $error_code != 2006 && $error_code != 2013 && $error_code != 2003) {
					throw $e;
				}

				self::dbg($e->getMessage() . "[" . $error_code . "]");

				self::dbg("Unable to connect. waiting 5 seconds before reconnect.");
				sleep(5);
			}
		}

		echo getmypid(), " Terminating...\n";
	}

	private function cron() {
		if (file_exists($this->cronlock)) {
			self::dbg(sprintf("Cron overlapped. StartTime: %s, OverlapTime: %s", file_get_contents($this->cronlock), date('r')));
			return;
		}

		$this->cronStartUp();

		$newsletters = Newsletter::getScheduled();
		if ($newsletters) {
			self::dbg(sprintf("Fetched %d newsletters", count($newsletters)));
			/* @var $newsletter Newsletter */
			foreach ($newsletters as $newsletter) {
				$newsletter->trigger();
				$this->sendNewsLetter($newsletter);
			}
		}

		$this->cronCleanUp();
		self::dbg('Terminate NewsletterMan Mailer cron..');
	}

	private function cronStartUp() {
		$written = file_put_contents($this->cronlock, date('r'));
		if (!$written) {
			throw new Exception("Unable to wrote to cron lock file {$this->cronlock}");
		}
		return $written;
	}

	private function cronCleanUp() {
		if (file_exists($this->cronlock)) {
			unlink($this->cronlock);
		}
	}

	private function sendNewsLetter(Newsletter $newsletter) {
		$sendmethod = $this->sendmethod;
		$msg = sprintf("Newsletter Proccessing [%d, %s, (scheduled: %s), using: %s] ....\n", $newsletter->Id, $newsletter->Subject, date('Y-m-d H:i:s', $newsletter->Schedule), $sendmethod);
		$start_time = microtime(true);
		self::dbg($msg);

		$phpmailer = $this->phpMailer();
		$phpmailer->From = $newsletter->SenderEmail;
		$phpmailer->FromName = $newsletter->SenderName;
		$phpmailer->Subject = $newsletter->Subject;

		if ($this->confirm_reading) {
			$phpmailer->ConfirmReadingTo = $newsletter->SenderEmail;
		}

		$phpmailer->clearReplyTos();
		$phpmailer->addReplyTo($newsletter->SenderEmail, $newsletter->SenderName);
		//$phpmailer->AltBody = striptags($newsletter->Message);

		list($deliveries, $failures, $logs) = $this->{$sendmethod}($newsletter, $phpmailer);
		array_unshift($logs, $msg);

		$newsletter->Deliveries = $deliveries;
		$newsletter->Failures = $failures;
		if (!$newsletter->Deliveries) {
			$newsletter->Triggered = 0;
		}
		$newsletter->save();
		$proccesed_secs = microtime(true) - $start_time;
		$processed_mins = round(($proccesed_secs / 60), 2);
		$proccesed_msg = sprintf("Newsletter Proccessed [%d, %s]: %d deliveries, %d failures in %s minutes\n", $newsletter->Id, $newsletter->Subject, $newsletter->Deliveries, $newsletter->Failures, $processed_mins);
		$logs[] = $proccesed_msg;
		$this->newsLetterLog($newsletter, $logs);
		self::dbg($proccesed_msg);
	}

	protected function sendInTO(Newsletter $newsletter, phpMailer $phpmailer) {
		$batch_limit = $this->batch_limit;
		$batch_offset = 0;
		$deliveries = $failures = 0;
		$logs = array();

		while ($this->restedBatch() && ($recipients = Recipient::getBatch($batch_offset, $batch_limit, $newsletter->Maillist))) {
			/* @var $recipient Recipient */
			foreach ($recipients as $recipient) {
				// replace place holders of recipient information
				$message = self::replace($newsletter->Message, (array) $recipient);
				$names = implode(' ', array($recipient->FirstName, $recipient->LastName));
				// If you are not able to send with PHPMailer, try vanilla mail function
				try {
					$this->clearPHPMailer($phpmailer);
					$phpmailer->addAddress($recipient->Email, $names);
					$phpmailer->Body = $message;
					$sent = $phpmailer->send();
				} catch (phpmailerException $e) {
					self::dbg('PHPMailer Exception [Send]: ' . $e->getMessage());
					$sent = $this->mail($newsletter->SenderEmail, $recipient->Email, $newsletter->Subject, $message);
				}

				if (!empty($sent)) {
					$deliveries++;
					$logs[] = sprintf("[NL.%d] Sent To: %s <%s> \n", $newsletter->Id, $names, $recipient->Email);
				} else {
					$failures++;
					$logs[] = sprintf("[NL.%d] Failed To: %s <%s> \n", $newsletter->Id, $names, $recipient->Email);
				}
			}

			$batch_offset += $batch_limit;
		}

		return array ($deliveries, $failures, $logs);
	}

	protected function sendInBCC(Newsletter $newsletter, phpMailer $phpmailer) {
		$batch_limit = $this->batch_limit;
		$batch_offset = 0;
		$deliveries = $failures = 0;
		$logs = array();

		$phpmailer->Body = $newsletter->Message;

		while ($this->restedBatch() && ($recipients = Recipient::getBatch($batch_offset, $batch_limit, $newsletter->Maillist))) {
			try {
				$batchfailures = 0;
				$this->clearPHPMailer($phpmailer);
				/* @var $recipient Recipient */
				$phpmailer->addAddress($newsletter->SenderEmail, $newsletter->SenderName);
				foreach ($recipients as $recipient) {
					$names = implode(' ', array($recipient->FirstName, $recipient->LastName));
					if ($phpmailer->validateAddress($recipient->Email)) {
						$phpmailer->addBCC($recipient->Email, $names);
						$logs[] = sprintf("[NL.%d] Sent To: %s <%s> \n", $newsletter->Id, $names, $recipient->Email);
					} else {
						$logs[] = sprintf("[NL.%d] Failed To: %s <%s> \n", $newsletter->Id, $names, $recipient->Email);
						$batchfailures++;
					}
				}
				$phpmailer->send();
				$deliveries += count($recipients) - $batchfailures;
				$failures += $batchfailures;
			} catch (Exception $e) {
				self::dbg('PHPMailer Exception [Send]: ' . $e->getMessage());
				$failures += count($recipients);
				foreach ($recipients as $recipient) {
					$names = implode(' ', array($recipient->FirstName, $recipient->LastName));
					$logs[] = sprintf("[NL.%d] Failed To: %s <%s> \n", $newsletter->Id, $names, $recipient->Email);
				}
			}

			$batch_offset += $batch_limit;
		}

		return array ($deliveries, $failures, $logs);
	}

	/**
	 * Get PHPMailer instance
	 *
	 * @staticvar PHPMailer $phpmailer
	 * @return PHPMailer
	 * @throws Exception
	 */
	private function phpMailer() {
		static $phpmailer;

		if (!is_null($phpmailer)) {
			return $phpmailer;
		}

		$phpmailer = new PHPMailer(true);

		// config settings
		$server = Config::get('smtp.server');
		$username = Config::get('smtp.username');
		$password = Config::get('smtp.password');
		$port = Config::get('smtp.port');
		$secure = Config::get('smtp.secure');

		$phpmailer->isSMTP();  // Set mailer to use SMTP
		$phpmailer->Host = $server;  // Specify main and backup server
		$phpmailer->SMTPAuth = true; // Enable SMTP authentication
		$phpmailer->Username = $username; // SMTP username
		$phpmailer->Password = $password;   // SMTP password
		if ($port) {
			$phpmailer->Port = $port;
		}
		if ($secure) {
			$phpmailer->SMTPSecure = $secure; // Enable encryption, 'tls, ssl' also accepted
		}

		$phpmailer->isHTML(true);
		$phpmailer->Priority = 1;
		$phpmailer->addCustomHeader("X-MSMail-Priority: High");
		$phpmailer->addCustomHeader("Importance: High");
		$phpmailer->CharSet = 'utf-8';

		return $phpmailer;
	}

	/**
	 * Signal handler
	 *
	 * @param integer $signo
	 */
	public function interrupt($signo) {
		switch ($signo) {
			// Set terminated flag to be able to terminate program securely
			// to prevent from terminating in the middle of the process
			// Use Ctrl+C to send interruption signal to a running program
			case SIGINT:
			case SIGTERM:
				$this->out = true;
				echo getmypid(), " Received termination signal\n";
				break;

			// switch the debug mode on/off
			// @example: $ kill -s SIGUSR1 <pid>
			case SIGUSR1:
				if ((self::$dbg = !self::$dbg))
					echo "\nEntering debug mode...\n";
				else
					echo "\nLeaving debug mode...\n";
				break;
		}
		$this->cronCleanUp();
	}

	public function shutdown() {
		$this->cronCleanUp();
	}

	/**
	 * Set interval in seconds for sending emails to a batch of recipients
	 *
	 * @param float $interval
	 */
	public function setBatchInterval($interval) {
		if (!is_numeric($interval) || $interval <= 0) {
			return;
		}
		$this->batch_interval = round($interval, 6);
	}

	/**
	 * Set what method will be used to send the emails
	 *
	 * @param string $method
	 */
	public function setSendMethod($method) {
		if (!$method || !in_array($method, array('sendInTO', 'sendInBCC'))) {
			return;
		}
		$this->sendmethod = $method;
	}

	public function setReadingConfirmation($value) {
		$this->confirm_reading = $value;
	}

	/**
	 * Set which config method was used
	 *
	 * @param string $config
	 */
	public function setConfigUsed($config) {
		$this->config_used = $config;
	}

	/**
	 * Set interval in seconds for checking newsletters
	 *
	 * @param float $interval
	 */
	public function setLoopInterval($interval) {
		if (!is_numeric($interval) || $interval <= 0) {
			return;
		}
		$this->loop_interval = round($interval, 6);
	}

	public function setBatchLimit($limit) {
		if (!is_int($limit) || $limit <= 0) {
			return;
		}
		$this->batch_limit = $limit;
	}

	private function newsLetterLog(Newsletter $newsletter, array $messages) {
		$logfile = NLM_DIR . '/logs/newsletter_' . $newsletter->Id . '.log';
		if (!is_file($logfile)) {
			file_put_contents($logfile, date('r') . "\n");
		}
		error_log(implode("", $messages), 3, $logfile);
	}

	/**
	 * Clear certain PHPMailer params
	 *
	 * @param PHPMailer $phpmailer
	 */
	private function clearPHPMailer(&$phpmailer) {
		$phpmailer->clearAddresses();
		$phpmailer->clearAllRecipients();
		$phpmailer->clearBCCs();
		$phpmailer->clearCCs();
	}

	private function mail($from, $to, $subject, $message) {
		$headers = array(
			'From' => $from,
			'Content-type' => 'text/html',
			'Priority' => 'Urgent',
			'Importance' => 'high',
			'X-MSMail-Priority' => 'High',
			'X-Priority' => 1,
			'X-Confirm-Reading-To' => $from,
		);

		return @mail($to, $subject, $message, $this->headersString($headers));
	}

	private function headersString(array $headers) {
		$str = "";
		foreach ($headers as $key => $value) {
			$str .= "$key: $value" . "\r\n";
		}
		return $str;
	}

	private function rested() {
		static $last_access;
		if (!is_null($last_access) && $this->loop_interval > ($usleep = (microtime(true) - $last_access))) {
			usleep(1000000 * ($this->loop_interval - $usleep));
		}

		$last_access = microtime(true);
		return true;
	}

	private function restedBatch() {
		static $last_batch_access;
		if (!is_null($last_batch_access) && $this->batch_interval > ($usleep = (microtime(true) - $last_batch_access))) {
			usleep(1000000 * ($this->batch_interval - $usleep));
		}

		$last_batch_access = microtime(true);
		return true;
	}

	/**
	 * Debug output
	 *
	 * @param string $str
	 */
	public static function dbg($str) {
		$message = "[" . date('Y-m-d H:i:s') . "] NewsletterMailMan: " . getmypid() . " " . $str . "\n";
		if (self::$dbg) {
			// list($ms, $s) = explode(' ', microtime());
			echo $message;
		}
		error_log($message);
	}

	/**
	 * Replace place holders of the form %{....} in a string
	 *
	 * @param string $string String with place holders
	 * @param string $data An associative array with keys being the place holder names
	 * @return string
	 */
	private static function replace($string, $data = array()) {
		foreach ($data as $find => $replace) {
			if (!is_string($replace)) {
				continue;
			}
			$find = '%{'.$find.'}';
			$string = str_replace($find, $replace, $string);
		}
		return $string;
	}

}
