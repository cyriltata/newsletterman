#!/usr/bin/php
<?php

require_once dirname(__FILE__) . '/../setup.php';

if (php_sapi_name() !== 'cli') {
	exit('Command line usage only');
}

$opts = getopt('dc:m:');
$as_deamon = isset($opts['d']);
$config_file = isset($opts['c']) ? $opts['c'] : null;

try {
	// If a config file was specified, use it
	if ($config_file && is_readable($config_file)) {
		//$config = array();
		require_once $config_file;
		Config::overwrite($config);
	}
	// Send the mail man on a mission
	$mailman = new MailMan();
	$mailman->setBatchInterval(Config::get('batch_interval'));
	$mailman->setBatchLimit(Config::get('batch_limit'));
	$mailman->setLoopInterval(Config::get('loop_interval'));
	$mailman->setSendMethod(Config::get('send_method'));
	$mailman->setErrorHandler();
	$mailman->run($as_deamon);
} catch (Exception $e) {
	error_log($e->getMessage());
	error_log($e->getTraceAsString());
	MailMan::dbg('Exit with error');
	exit(1);
}

