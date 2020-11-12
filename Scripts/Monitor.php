<?php

use Shared\Notification;
use Shared\Config;
use Shared\Loggers;
use Shared\Database;
use Stores\Asda\AsdaMonitor;
use Stores\Asda\AsdaProducts;

ini_set('memory_limit', '-1');

require_once __DIR__.'/../vendor/autoload.php';

// TODO - Product Update Daily. Reviews, New Suggestions, Price Change, Promotions

// 1. Get All unique product moniored, groceries. (Not In Notifications Table).
// 2. For each product, fetch info and compare.
// 3. Insert new promotion if exists.
// 4. If changed, add to notifications table. (API Will fetch from here and send to users)

// Update reviews, description

// 1. For each promotion, check if still exists and nothing changed.

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
    $asda_monitor = new AsdaMonitor($config, $logger, $database, null, $notification);
    $asda_monitor->monitor_products();
    $logger->notice("---------- Asda Monitoring Complete ---------- ");
}

$logger->notice("---------------------------- Monitor Script END ------------------------------");

?>