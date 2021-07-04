<?php

require_once './vendor/autoload.php';

use Collection\Services\SharedNutritionService;
use Collection\Services\SharedProductPriceService;
use Collection\Services\SharedProductService;
use Services\DatabaseService;
use Models\Product\ProductModel;
use Models\Product\ProductPriceModel;
use Services\ConfigService;
use Services\LoggerService;
use Services\RequestService;

use Collection\Services\SharedRegionService;

// use Collection\Supermarkets\Asda\Groceries\Products\Products as AsdaProducts;
use Collection\Loblaws\Groceries\Products\Products as CanadianSuperStoreProducts;

$config_service = new ConfigService();

$config_service->set('database.env', 'prod');

$log = new LoggerService('Migration');
$logger = $log->logger_handler;

$request = new RequestService($config_service, $logger);

$database_service = new DatabaseService($config_service, $logger);

$product_model = new ProductModel($database_service);
$product_price_model = new ProductPriceModel($database_service);

$shared_product_service = new SharedProductService($database_service);
$shared_product_price_service = new SharedProductPriceService($database_service);

$logger->debug('------ Product Prices Migration Start ------');


// $products = $product_model->select(['products.*'])->where(['store_type_id' => 2])->get();
$products = $product_model->select(['products.*'])->where(['store_type_id' => 2])->get();

$region_service = new SharedRegionService($database_service);

$ca_product_collection = new CanadianSuperStoreProducts($config_service, $logger, $database_service, $region_service);

$nutrition_service = new SharedNutritionService($database_service);

// $database_service->start_transaction();

foreach($products as $index => $product){
    $database_service->start_transaction();

    $product_id = $product->id;

    $name = $product->name;
    $site_product_id = $product->site_product_id;
    $store_type_id = $product->store_type_id;

    $logger->debug("--- Start: {$index} | Product: [$product_id] $name");

    $product = $ca_product_collection->product_details($site_product_id, true); 
    $prices = $product->prices ?? [];

    if(!is_null($product)){
        $nutrition_service->create_nutritions($product_id, $product);
    } else {
        $logger->error('No Product Details Found');
    }

  
    $logger->debug("--- Complete: {$index} | Product: [$product_id] $name");

    $database_service->commit_transaction();
}


$logger->debug('------ Product Prices Migration Complete ------');

?>