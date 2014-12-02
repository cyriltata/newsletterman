<?php

// Define constants
define('NLM_DIR', dirname(__FILE__));
define('NLM_CONDIF_DIR', NLM_DIR . '/config');

// Load required vendors
$vendors = NLM_DIR . '/vendor/autoload.php';
if (!file_exists($vendors)) {
	echo "Missing required dependencies: Install composer, and run the command below\n";
	echo "Command: cd ".NLM_DIR."; composer install\n";
	exit(1);
}
require_once $vendors;

// Include Config
/* @var $config array */
$config = array();
$config['database'] = array();
$config_file = NLM_CONDIF_DIR . '/config.php';
if (!file_exists($config_file)) {
	$config_dist = NLM_CONDIF_DIR . '/config.dist.php';
	echo "Config is missing: Create $config_file file from $config_dist and set the required parameters.\n";
	echo "Command: cp $config_dist $config_file \n";
	exit(1);
}
require_once $config_file;

// Include classes
foreach (($classes = glob(NLM_DIR . '/classes/*.php')) as $file) {
	require_once $file;
}

// Initialize config
Config::initialize($config);

// Touch log files
foreach ($config['logs'] as $type => $logfile) {
	if (!file_exists($logfile)) {
		file_put_contents($logfile, date('r'));
	}
}

if (($file = Config::get('logs.errors')) && is_file($file)) {
	ini_set('log_errors', 1);
	ini_set('error_log', $file);
}

if ($timezone = Config::get('timezone')) {
	date_default_timezone_set($timezone);
}

mb_internal_encoding('UTF-8');
