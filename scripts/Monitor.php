<?php

ini_set('memory_limit', '-1');

require_once __DIR__.'/../vendor/autoload.php';

use Models\Store\StoreTypeModel;
use Monitors\MonitorProducts;
use Monitors\MonitorStores;
use Services\CLIService;
use Services\Notification;
use Services\Config;
use Services\Loggers;
use Services\Database;

use Collection\Supermarkets\Asda\Groceries\Products\Products as AsdaProducts;
use Collection\Supermarkets\Canadian_Superstore\Groceries\Products\Products as CanadianSuperStoreProducts;

use Collection\Supermarkets\Asda\Stores\Stores as AsdaStores;
use Collection\Supermarkets\Canadian_Superstore\Stores\Stores as CanadianSuperStoreStores;

$cli = new CLIService();
$cli->run();

$store_type = ucwords($cli->get_store_type());
$monitor_type = strtolower($cli->get_monitor_type());

$unique_products = [];

$config = new Config();
$logging = new Loggers('Monitor', $store_type);

$logger = $logging->logger_handler;

$database = new Database($config,$logger);
$notification = new Notification($config, $logger);


$logger->notice("---------------------------- Monitor Script Start ----------------------------");

try {

    if($monitor_type != 'stores' && $monitor_type  != 'products'){
        return exit($logger->error('Unknown Run Type: '. $monitor_type));
    }

    $logger->debug('Checking All Store Details Match');

    $store_conf = $config->get('stores.' . str_replace(' ', '_', strtolower($store_type)));

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
                $product_collection = new AsdaProducts($config, $logger, $database);
            } else if($store_type_id == 2){
                $product_collection = new CanadianSuperStoreProducts($config, $logger, $database);
            }
            
            if(is_null($product_collection)){
                return exit($logger->error('No Product Collection Type Found For Store: ' . $store_type_id));
            }

            $monitor = new MonitorProducts($config, $logger, $database, $product_collection );

            $monitor->monitor_products($store_conf);

        } else {
            // Runs every sunday morning. 4am. [ 0 4 * * SUN ]

            if($store_type_id == 1){
                $store_collection = new AsdaStores($config, $logger, $database);
            } else if($store_type_id == 2){
                $store_collection = new CanadianSuperStoreStores($config, $logger, $database);
            }
            
            if(is_null($store_collection)){
                return exit($logger->error('No Store Collection Type Found For Store: ' . $store_type_id));
            }

            $monitor = new MonitorStores($config, $logger, $database, $store_collection );

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