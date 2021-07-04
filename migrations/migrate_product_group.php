<?php

require_once './vendor/autoload.php';

use Collection\Services\SharedProductGroupService;
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
use Models\Category\CategoryProductModel;

$config_service = new ConfigService();

$log = new LoggerService('Migration');
$logger = $log->logger_handler;

$request = new RequestService($config_service, $logger);

$database_service = new DatabaseService($config_service, $logger);

$category_product_model = new CategoryProductModel($database_service);
$product_model = new ProductModel($database_service);
$product_price_model = new ProductPriceModel($database_service);

$shared_product_service = new SharedProductService($database_service);
$shared_product_price_service = new SharedProductPriceService($database_service);
$shared_product_group_service = new SharedProductGroupService($database_service);

// 1. Create Regions
// 2. Create tables, make table changes
// 3. For each product, for these regions. Create product_prices.

$logger->debug('------ Product Groups Migration Start ------');


// $products = $product_model->select(['products.*'])->where(['store_type_id' => 2])->where_raw(['id = 55078'])->get();
// $products = $product_model->select(['products.*'])->join('product_prices', 'products.id', 'product_prices.product_id')->where(['store_type_id' => 2])->where_raw(['product_prices.product_id is null'])->get();
$products = $product_model->select(['products.*', 'category_products.id as category_products_id', 'category_products.child_category_id'])->join('category_products', 'products.id', 'category_products.product_id')->where(['store_type_id' => 2, 'product_group_id' => 0])->get();

$store_type_names = [
    1 => 'Asda',
    2 => 'Real Canadian Superstore'
];

// $asda_product_collection = new AsdaProducts($config_service, $logger, $database_service);

$region_service = new SharedRegionService($database_service);

$ca_product_collection = new CanadianSuperStoreProducts($config_service, $logger, $database_service, $region_service);

// $database_service->start_transaction();

foreach($products as $index => $product){
    $database_service->start_transaction();

    $product_id = $product->id;
    $child_category_id = $product->child_category_id;
    $store_type_id = $product->store_type_id;
    $category_products_id = $product->category_products_id;

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

        $product_group_id = $shared_product_group_service->create($product, $child_category_id, $store_type_id);

        $category_product_model->where(['id' => $category_products_id, 'product_group_id' => 0])->update(['product_group_id' => $product_group_id]);
    } else {
        $logger->error('No Product Details Found');
    }

  
    $logger->debug("--- Complete: $store_type_name | {$index} | Product: [$product_id] $name");

    $database_service->commit_transaction();
}

$logger->debug('------ Product Groups Migration Complete ------');

?>