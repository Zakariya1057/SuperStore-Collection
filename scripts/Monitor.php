<?php

ini_set('memory_limit', '-1');

require_once __DIR__.'/../vendor/autoload.php';

use Collection\Services\SharedRegionService;
use Monitors\MonitorProducts;
use Monitors\MonitorStores;
use Services\CLIService;
use Services\ConfigService;
use Services\DatabaseService;

use Collection\Supermarkets\Asda\Groceries\Products\Products as AsdaProducts;
use Collection\Loblaws\Groceries\Products\Products as CanadianSuperStoreProducts;

use Collection\Supermarkets\Asda\Services\StoreService as AsdaStoreService;
use Collection\Loblaws\Services\StoreService as CanadianSuperstoreStoreService;

use Services\LoggerService;
use Services\NotificationService;

$cli = new CLIService();
$cli->run();

$store_type = ucwords($cli->get_store_type());
$monitor_type = strtolower($cli->get_monitor_type());

$unique_products = [];

$config_service = new ConfigService();
$logger_service = new LoggerService('Monitor', $store_type);

$logger = $logger_service->logger_handler;

$database_service = new DatabaseService($config_service, $logger);
$notification = new NotificationService($config_service, $logger);


$logger->notice("---------------------------- Monitor Script Start ----------------------------");

try {
    
    if($monitor_type != 'stores' && $monitor_type  != 'products'){
        die('Unknown Run Type: '. $monitor_type);
    }

    if(scriptRunning($monitor_type)){
        exit("Monitor Script already running. Exiting now.\n");
    }

    $store_conf = $config_service->get('companies.' . str_replace(' ', '_', strtolower($store_type)) . '.settings' );

    if(is_null($store_conf)){
        throw new Exception('No Store Type Found In Configs With Name: ' . $store_type);
    }

    // Create another script runs less frequently. Check if unaivavle products available vice versa
    if($store_conf->run && $store_conf->monitor){
        $logger->notice("---------- Loblaws Monitoring Start ---------- ");

        $logger->notice("--- Monitoring Type: $monitor_type ");

        if($monitor_type == 'products'){
            // Runs every 4 hours During Day.  [ 0 9,13,17,20 * * * ]

            $region_service = new SharedRegionService($database_service);
            $product_collection = new CanadianSuperStoreProducts($config_service, $logger, $database_service, $region_service);

            $monitor = new MonitorProducts($config_service, $logger, $database_service, $product_collection );

            $monitor->monitor_products($store_conf);

        } else {
            // Runs every sunday morning. 4am. [ 0 4 * * SUN ]

            $store_service = new CanadianSuperstoreStoreService($config_service, $logger, $database_service);

            $monitor = new MonitorStores($config_service, $logger, $database_service, $store_service );

            $monitor->monitor_stores($store_conf);

        }

        $logger->notice("---------- Loblaws Monitoring Complete ---------- ");
    }

} catch(Exception $e){
    $logger->critical('Script Monitor Error: '. $e->getMessage());
}


$logger->notice("---------------------------- Monitor Script END ------------------------------");


function scriptRunning($type){
    $output = shell_exec('ps aux | grep php');
    $regex = "(php scripts\/Monitor\.php --store=Real Canadian Superstore --type=$type)";
    preg_match_all("/$regex/i", $output, $matches);
    return count($matches[0]) > 1;
}

?>