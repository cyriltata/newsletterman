#!/usr/bin/php
<?php

require_once dirname(__FILE__) . '/../setup.php';

if (php_sapi_name() !== 'cli') {
	exit('Command line usage only');
}

try {
	$mailer = new Mailer();
	$mailer->setBatchInterval(Config::get('batch_interval'));
	$mailer->setBatchLimit(Config::get('batch_limit'));
	$mailer->setLoopInterval(Config::get('loop_interval'));
	$mailer->setErrorHandler();
	$mailer->run();
} catch (Exception $e) {
	error_log($e->getMessage());
	error_log($e->getTraceAsString());
	exit(1);
}

