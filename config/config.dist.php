<?php

/* 
 * Config Parameters for NewsletterMan
 * NOTE: For all database configurations, the table must have ALL the indicated fields
 */

// Database configuration where newsletters reside
$config['database']['newsletters'] = array(
	// Connection parameters
	'host' => 'host',
	'username' => 'username',
	'password' => 'password',
	'dbname' => 'dbname',
	'port' => 3306,
	'charset' => 'utf8',

	// Table and field definitions
	'table' => 'newsletters',
	'id_field' => 'id',
	'subject_field' => 'title',
	'message_field' => 'content',
	'schedule_field' => 'schedule',
	'triggered_field' => 'triggered',
	'sender_name_field' => 'sender_name',
	'sender_email_field' => 'sender_email',
	'deliveries_field' => 'deliveries', 
	'failures_field' => 'failures',
);

// Database configuration where newsletters recipients resider
$config['database']['recipients'] = array(
	// Connection parameters
	'host' => 'host',
	'username' => 'username',
	'password' => 'password',
	'dbname' => 'dbname',
	'port' => 3306,
	'charset' => 'utf8',

	// Table and field definitions
	'table' => 'newsletters_mailinglist',
	'id_field' => 'id',
	'names_field' => 'names',
	'email_field' => 'email',
);

// Log destination
$config['logs'] = array(
	'errors' => NLM_DIR . '/logs/errors.log'
);

// Timezone
$config['timezone'] = 'Europe/Berlin';

// Interval in seconds to check for scheduled newsletters in DB
$config['loop_interval'] = 10;

// Interval in seconds to rest before processing next recipients batch
$config['batch_interval'] = 5;

// How many emails should be sent out in a batch before resting
$config['batch_limit'] = 20;

// SMTP settings
$config['smtp'] = array(
	'server' => '',
	'username' => '',
	'password' => '',
	'port' => '',
	'secure' => '', //'tls' or 'ssl' or leave blank
);