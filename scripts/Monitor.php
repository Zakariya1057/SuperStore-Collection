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
use Collection\Supermarkets\Canadian_Superstore\Groceries\Products\Products as CanadianSuperStoreProducts;

use Collection\Supermarkets\Asda\Services\StoreService as AsdaStoreService;
use Collection\Supermarkets\Canadian_Superstore\Services\StoreService as CanadianSuperstoreStoreService;

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
        return exit($logger->error('Unknown Run Type: '. $monitor_type));
    }

    $logger->debug('Checking All Store Details Match');

    $store_conf = $config_service->get('stores.' . str_replace(' ', '_', strtolower($store_type)));

    if(is_null($store_conf)){
        throw new Exception('No Store Type Found In Configs With Name: ' . $store_type);
    }

    $store_type_id = $store_conf->store_type_id;

    // Create another script runs less frequently. Check if unaivavle products available vice versa
    if($store_conf->run && $store_conf->monitor){
        $logger->notice("---------- [$store_type_id] $store_type Monitoring Start ---------- ");

        $logger->notice("--- Monitoring Type: $monitor_type ");

        if($monitor_type == 'products'){
            // Runs every 3 hours.  [ 0 */3 * * * ]

            if($store_type_id == 1){
                $product_collection = new AsdaProducts($config_service, $logger, $database_service);
            } else if($store_type_id == 2){
                $region_service = new SharedRegionService($database_service);
                $product_collection = new CanadianSuperStoreProducts($config_service, $logger, $database_service, $region_service);
            }
            
            if(is_null($product_collection)){
                return exit($logger->error('No Product Collection Type Found For Store: ' . $store_type_id));
            }

            $monitor = new MonitorProducts($config_service, $logger, $database_service, $product_collection );

            $monitor->monitor_products($store_conf);

        } else {
            // Runs every sunday morning. 4am. [ 0 4 * * SUN ]

            if($store_type_id == 1){
                $store_service = new AsdaStoreService($config_service, $logger, $database_service);
            } else if($store_type_id == 2){
                $store_service = new CanadianSuperstoreStoreService($config_service, $logger, $database_service);
            }
            
            if(is_null($store_service)){
                return exit($logger->error('No Store Collection Type Found For Store: ' . $store_type_id));
            }

            $monitor = new MonitorStores($config_service, $logger, $database_service, $store_service );

            $monitor->monitor_stores($store_conf);

        }

        $logger->notice("---------- [$store_type_id] $store_type Monitoring Complete ---------- ");
    } else {
        $logger->notice("--- Monitoring Disabled: $store_type");
    }

} catch(Exception $e){
    $logger->critical('Script Monitor Error: '. $e->getMessage());
}


$logger->notice("---------------------------- Monitor Script END ------------------------------");

?>