<?php

require_once './vendor/autoload.php';

use Models\Category\CategoryModel;
use Models\Featured\FeaturedModel;
use Services\DatabaseService;
use Models\Product\ProductModel;
use Models\Product\ProductPriceModel;
use Models\Product\PromotionModel;
use Services\ConfigService;
use Services\LoggerService;

$config_service = new ConfigService();

$log = new LoggerService('Migration');
$logger = $log->logger_handler;

$config_service->set('log_query', false);

$database_service = new DatabaseService($config_service, $logger);

$product_model = new ProductModel($database_service);
$product_price_model = new ProductPriceModel($database_service);

$category_model = new CategoryModel($database_service);

$promotion_model = new PromotionModel($database_service);

$featured_model = new FeaturedModel($database_service);

// 1. Create Regions
// 2. Create tables, make table changes
// 3. For each product, for these regions. Create product_prices.

$logger->debug('------ Featured Migration Start ------');

// Get all featued_items for given region, insert for other regions with similar data.

$database_service->start_transaction();

$promotions = $promotion_model
->select(['title', 'site_category_id'])
->where_in('id', [
    1257,
    1314,
    1410,
    1420,
    1446,
    1718,
    2424,
    2856,
    2979,
    3376
])->get();

$regions = [9, 10, 11, 12, 13];

foreach($promotions as $promotion){

    $where = [];

    foreach($promotion as $field => $value){
        $where[$field] = $value;
    }

    foreach($regions as $region){
        $new_where = $where;
        $new_where['region_id'] = $region;

        $region_promotion = $promotion_model->where($new_where)->first();

        if(!is_null($region_promotion)){
            $region_promotion_id = $region_promotion->id;

            echo("insert into featured_items(featured_id, type, store_type_id, region_id, week, year) values ($region_promotion_id, 'promotions', 2, $region, '15','2021');\n");
        }
    }

}

// $database_service->commit_transaction();

$logger->debug('------ Featured Migration Complete ------');

?>