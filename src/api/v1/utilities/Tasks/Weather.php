<?php
require __DIR__ . '/../../../vendor/autoload.php';

date_default_timezone_set('Europe/London');

$dotenv = \Dotenv\Dotenv::createImmutable(dirname(__FILE__). '/../../');
$dotenv->load();

$timestamp = date("Y-m-d H:i:s", time());

$this->ada = new \Dependency\Databases\Rainglow();
echo 'ran';
