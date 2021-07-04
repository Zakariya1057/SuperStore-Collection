<?php

require_once './vendor/autoload.php';

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

$log = new LoggerService('Migration');
$logger = $log->logger_handler;

$request = new RequestService($config_service, $logger);

$database_service = new DatabaseService($config_service, $logger);

$product_model = new ProductModel($database_service);
$product_price_model = new ProductPriceModel($database_service);

$shared_product_service = new SharedProductService($database_service);
$shared_product_price_service = new SharedProductPriceService($database_service);

// 1. Create Regions
// 2. Create tables, make table changes
// 3. For each product, for these regions. Create product_prices.

$logger->debug('------ Product Prices Migration Start ------');


$products = $product_model->select(['products.*'])->where(['store_type_id' => 2])->get();
// $products = $product_model->select(['products.*'])->join('product_prices', 'products.id', 'product_prices.product_id')->where(['store_type_id' => 2])->where_raw(['product_prices.product_id is null'])->get();
// $products = $product_model->select(['products.*'])->join('product_prices', 'products.id', 'product_prices.product_id')->where(['store_type_id' => 2])->where_raw(['product_prices.product_id = 228'])->limit(100)->get();

$store_type_names = [
    1 => 'Asda',
    2 => 'Real Canadian Superstore'
];

// $asda_product_collection = new AsdaProducts($config_service, $logger, $database_service);

$region_service = new SharedRegionService($database_service);

$ca_product_collection = new CanadianSuperStoreProducts($config_service, $logger, $database_service, $region_service);

foreach($products as $index => $product){
    $database_service->start_transaction();

    $product_id = $product->id;

    $name = $product->name;
    $site_product_id = $product->site_product_id;
    $store_type_id = $product->store_type_id;
    $store_type_name = $store_type_names[$product->store_type_id];

    $logger->debug("--- Start: $store_type_name | {$index} | Product: [$product_id] $name");

    $product = $ca_product_collection->product_details($site_product_id, true); 
    $prices = $product->prices ?? [];


    if(!is_null($product)){
        $shared_product_service->create_product_promotions($product, true);
        $shared_product_price_service->create_prices($product_id, $product, true);

        // foreach($prices as $product_price){
        //     $product_price->product_id = $product_id;
        //     $product_price->insert_ignore = true;
        //     $product_price->save();
        // }

    } else {
        $logger->error('No Product Details Found');
    }

  
    $logger->debug("--- Complete: $store_type_name | {$index} | Product: [$product_id] $name");

    $database_service->commit_transaction();
}


$logger->debug('------ Product Prices Migration Complete ------');

?>