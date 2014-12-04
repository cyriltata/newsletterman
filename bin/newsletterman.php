#!/usr/bin/php
<?php

require_once dirname(__FILE__) . '/../setup.php';

if (php_sapi_name() !== 'cli') {
	exit('Command line usage only');
}

$opts = getopt('d');
$as_deamon = isset($opts['d']);
try {
	$mailman = new MailMan();
	$mailman->setBatchInterval(Config::get('batch_interval'));
	$mailman->setBatchLimit(Config::get('batch_limit'));
	$mailman->setLoopInterval(Config::get('loop_interval'));
	$mailman->setErrorHandler();
	$mailman->run($as_deamon);
} catch (Exception $e) {
	error_log($e->getMessage());
	error_log($e->getTraceAsString());
	exit(1);
}

