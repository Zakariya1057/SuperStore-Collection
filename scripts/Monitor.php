<?php

use Models\Store\StoreModel;
use Models\Store\StoreTypeModel;
use Monitors\MonitorProducts;
use Monitors\MonitorStores;
use Services\Notification;
use Services\Config;
use Services\Loggers;
use Services\Database;

use Supermarkets\Asda\Groceries\Products\Products as AsdaProducts;
use Supermarkets\Canadian_Superstore\Groceries\Products\Products as CanadianSuperStoreProducts;

use Supermarkets\Asda\Stores\Stores as AsdaStores;
use Supermarkets\Canadian_Superstore\Stores\Stores as CanadianSuperStoreStores;

ini_set('memory_limit', '-1');

require_once __DIR__.'/../vendor/autoload.php';

$unique_products = [];

$config = new Config();
$logging = new Loggers();

$logger = $logging->logger_handler;

$database = new Database($config,$logger);
$notification = new Notification($config, $logger);


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


    $logger->debug('Checking All Store Details Match');

    $store_model = new StoreTypeModel($database);

    $stores = $store_model->select('id, name')->get();

    $configs = [
        1 => 'asda',
        2 => 'canadian_superstore'
    ];

    foreach($stores as $store_type){

        $store_type_id = $store_type->id;
        $store_name = $store_type->name;

        $config_key = $configs[$store_type_id];

        $store_conf = $config->get('stores.' . $config_key);

        // Create another script runs less frequently. Check if unaivavle products available vice versa
        if($store_conf->run && $store_conf->monitor){
            $logger->notice("---------- $store_name Monitoring Start ---------- ");


            if($type == 'products'){
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

                $monitor->monitor_products($store_type);

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

                $monitor->monitor_stores($store_type);

            }

            $logger->notice("---------- $store_name Monitoring Complete ---------- ");
        } else {
            $logger->notice("--- Monitoring Disabled: $store_name");
        }
    }

} catch(Exception $e){
    $logger->critical('Script Monitor Error: '. $e->getMessage());
}


$logger->notice("---------------------------- Monitor Script END ------------------------------");

?>