<?php

use Models\Store\StoreModel;
use Models\Store\StoreTypeModel;
use Monitors\MonitorProducts;
use Services\Notification;
use Services\Config;
use Services\Loggers;
use Services\Database;

use Supermarkets\Asda\Groceries\Products\Products as AsdaProducts;
use Supermarkets\Canadian_Superstore\CanadianSuperstore;
use Supermarkets\Canadian_Superstore\Groceries\Products\Products as CanadianSuperStoreProducts;

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


    $logger->error('Getting All Stores');

    // $stores = [1,2];

    $store_model = new StoreTypeModel($database);

    $stores = $store_model->select('id, name')->where(['id' => 2])->get();

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

                // $asda_monitor = new MonitorProducts($config, $logger, $database, null, $notification);
                // $asda_monitor->monitor_products();

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
                // $asda_monitor = new MonitorStores($config, $logger, $database, null);
                // $asda_monitor->monitor_stores();

                // if($store_type_id == 1){
                //     $product_collection = new AsdaProducts($config, $logger, $database);
                // } else if($store_type_id == 2){
                //     // $product_collection = new Products($config, $logger, $database);
                // }

            }

            $logger->notice("---------- $store_name Monitoring Complete ---------- ");
        }

    }


} catch(Exception $e){
    $logger->critical('Script Monitor Error: '. $e->getMessage());
}


$logger->notice("---------------------------- Monitor Script END ------------------------------");

?>