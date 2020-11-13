<?php

use Shared\Notification;
use Shared\Config;
use Shared\Loggers;
use Shared\Database;
use Stores\Asda\AsdaMonitorProducts;

ini_set('memory_limit', '-1');

require_once __DIR__.'/../vendor/autoload.php';

$unique_products = [];

$config = new Config();
$logging = new Loggers();

$logger = $logging->logger_handler;

$database = new Database($config,$logger);
$notification = new Notification($config, $logger);

$asda_conf = $config->get('asda');

$logger->notice("---------------------------- Monitor Script Start ----------------------------");

if($asda_conf->run && $asda_conf->monitor){
    $logger->notice("---------- Asda Monitoring Start ---------- ");
    $asda_monitor = new AsdaMonitorProducts($config, $logger, $database, null, $notification);
    $asda_monitor->monitor_products();


    $logger->notice("---------- Asda Monitoring Complete ---------- ");
}

$logger->notice("---------------------------- Monitor Script END ------------------------------");

?>