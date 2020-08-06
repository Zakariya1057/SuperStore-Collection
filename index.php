<?php

require_once __DIR__.'/vendor/autoload.php';

use Shared\Config;
use Shared\Loggers;
use Shared\Database;
use Stores\Asda\AsdaGroceries;

$config = new Config();
$log = new Loggers();
$database = new Database();

$logger = $log->logger_handler;

$logger->notice("---------------------------- Script Starting ----------------------------");

$asda_conf = $config->get('asda');

if($asda_conf->run){
    $logger->notice("Asda Scraping Start");
    $asda = new AsdaGroceries($config,$logger,$database);

    // Scrape all categories, including promotions. Ignore duplicates products.
    // This continues where it left off, remembring where it was last time it ran. It keeps its own run history. 
    // Last category it was on and last product.
    if($asda_conf->groceries){
        $asda->groceries();
    }

    //Searches For new promotions.
    if($asda_conf->promotions){
        // $logger->notice("Asda Promotions Start");
    }

    $logger->notice("Asda Scraping Complete");
}

$logger->notice("---------------------------- Script Complete ----------------------------");

?>