<?php

use Services\Notification;
use Services\Config;
use Services\Loggers;
use Services\Database;
use Supermarkets\Asda\Monitors\MonitorProducts;
use Supermarkets\Asda\Monitors\MonitorStores;

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

try {

    $arguments = $argv;

    if(count($arguments) > 1){
        $type = strtolower($arguments[1]);

        if($type != 'stores' && $type != 'products'){
            return exit($logger->error('Unknown Run Type: '.$type));
        }
    } else {
        return exit($logger->error('Script Monitor Type Required: Products/Stores'));
    }


    // Create another script runs less frequently. Check if unaivavle products available vice versa
    if($asda_conf->run && $asda_conf->monitor){
        $logger->notice("---------- Asda Monitoring Start ---------- ");


        if($type == 'products'){
            // Runs every 3 hours.  [ 0 */3 * * * ]
            $asda_monitor = new MonitorProducts($config, $logger, $database, null, $notification);
            $asda_monitor->monitor_products();
        } else {
            // Runs every sunday morning. 4am. [ 0 4 * * SUN ]
            $asda_monitor = new MonitorStores($config, $logger, $database, null);
            $asda_monitor->monitor_stores();
        }

        $logger->notice("---------- Asda Monitoring Complete ---------- ");
    }

} catch(Exception $e){
    $logger->critical('Script Monitor Error: '. $e->getMessage());
}


$logger->notice("---------------------------- Monitor Script END ------------------------------");

?>