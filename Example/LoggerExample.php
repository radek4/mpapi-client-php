<?php
use MPAPI\Services\Client;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

require __DIR__ . '/../vendor/autoload.php';

// initialize API client
$mpApiClient = new Client('your_client_id');

if (class_exists('Logger')) {
	// Create instance of monolog logger
	$logger = new Logger('loggerName');
	$logger->pushHandler(new StreamHandler('/var/log/elog.log', Logger::INFO));

	// set logger into MP API client
	$mpApiClient->setLogger($logger);
}