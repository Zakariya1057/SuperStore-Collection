<?php

require_once './vendor/autoload.php';

use Services\DatabaseService;
use Services\ConfigService;
use Services\LoggerService;
use Services\RequestService;
use Models\Product\PromotionModel;

$config_service = new ConfigService();

$log = new LoggerService('Migration');
$logger = $log->logger_handler;

$request = new RequestService($config_service, $logger);

$database_service = new DatabaseService($config_service, $logger);

$promotion_model = new PromotionModel($database_service);

// 1. Create Regions
// 2. Create tables, make table changes
// 3. For each product, for these regions. Create product_prices.

$logger->debug('------ Promotion Migration Start ------');

$promotions = $promotion_model->like(['name' => '%limit%'])->where(['store_type_id' => 2])->get();

$database_service->start_transaction();

foreach($promotions as $promotion){
    $promotion_id = $promotion->id;
    $promotion_name = $promotion->name;
    preg_match('/(\d+\.*\d*) LIMIT (\d+)/', $promotion_name, $promotion_matches);

    $logger->debug($promotion_name);

    $promotion_model->where(['id' => $promotion_id])->update(['price' => $promotion_matches[1], 'maximum' => $promotion_matches[2]]);   
}


$database_service->commit_transaction();

$logger->debug('------ Promotion Migration Complete ------');

?>