<?php

require_once __DIR__.'/vendor/autoload.php';

// DONE - Reviews Table
// DONE - Related Products
// DONE - Main, Locations Stores Table
// DONE - Ingredients Table
// DONE - Exclude Haram Products
// TODO - Resume Scraping

// TODO - Promotions Table

use Shared\Config;
use Shared\Loggers;
use Shared\Database;

use Stores\Asda\Asda;


$config = new Config();
$log = new Loggers();
$database = new Database();

$logger = $log->logger_handler;

$logger->notice("---------------------------- Script Start ----------------------------");

$asda_conf = $config->get('asda');

if($config->get('env') == 'dev'){
    $logger->notice('Running In Development Environment.');
} else {
    $logger->notice('Running In Live Environment.');
}

if($asda_conf->run){
    $logger->notice("Asda Scraping Start");
    $asda = new Asda($config,$logger,$database);

    // Scrape all categories, including promotions. Ignore duplicates products.
    // This continues where it left off, remembring where it was last time it ran. It keeps its own run history. 
    // Last category it was on and last product.

    if($asda_conf->stores){
        //Get all stores in given city
        $asda->stores();
    }

    if($asda_conf->groceries){
        //Get all product sold on site
        $asda->groceries();
    }

    //Searches For new promotions.
    if($asda_conf->promotions){
        //Get new promotions. Update old ones
        $asda->promotions();
    }

    if($asda_conf->recommended){
        //Get all similar Products.
        $asda->recommended();
    }

    $logger->notice("Asda Scraping Complete");
}

$logger->notice("---------------------------- Script Complete ----------------------------");

?>