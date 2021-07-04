<?php

require_once './vendor/autoload.php';

use Collection\Services\SharedStoreService;
use Collection\Loblaws\Services\FlyerService;
use Services\DatabaseService;
use Models\Store\LocationModel;
use Services\ConfigService;
use Services\LoggerService;
use Services\RequestService;
use Models\Store\StoreModel;

use Collection\Loblaws\Services\StoreService as CanadianSuperstoreStoreService;

$config_service = new ConfigService();

$log = new LoggerService('Migration');
$logger = $log->logger_handler;

$request = new RequestService($config_service, $logger);

$database_service = new DatabaseService($config_service, $logger);

$store_model = new StoreModel($database_service);
$location_model = new LocationModel($database_service);

$store_collection = new CanadianSuperstoreStoreService($config_service, $logger, $database_service);
$flyer_service = new FlyerService($config_service, $logger, $database_service);
$shared_store_service = new SharedStoreService($database_service);

$logger->debug('------ Product Prices Migration Start ------');

// $database_service->start_transaction();

$stores = $store_model->where_in('stores.id', [37,53,59,70,87,95,97,113])->join('store_locations', 'stores.id', 'store_locations.store_id')->get();

foreach($stores as $store){
    $store_id = $store->id;
    $site_store_id = $store->site_store_id;

    $store_details = $store_collection->store_details($site_store_id);
    
    $region = $store_details->region;

    $region_name = $region->name;

    $region_id = $region->where(['name' => $region_name])->first()->id;

    $location = $store_details->location;

    $location->where(['store_id' => $store_id])->update(['region_id' => $region_id]);

    $flyers = $flyer_service->get_flyers($site_store_id, $store_id);
    $shared_store_service->create_flyers($flyers, $store_id);
}

// $database_service->commit_transaction();

$logger->debug('------ Product Prices Migration Complete ------');

?>