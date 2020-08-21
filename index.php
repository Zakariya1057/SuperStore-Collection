<?php

require_once __DIR__.'/vendor/autoload.php';

// DONE - Reviews Table
// DONE - Related Products
// DONE - Main, Locations Stores Table
// DONE - Ingredients Table
// DONE - Exclude Haram Products
// DONE - Resume Scraping

// TODO - New Promotions

use Shared\Config;
use Shared\Loggers;
use Shared\Database;
use Shared\Remember;
use Stores\Asda\Asda;


$config = new Config();
$log = new Loggers();
$logger = $log->logger_handler;

$database = new Database($config,$logger);

$remember = new Remember($config,$logger);

$logger->notice("---------------------------- Script Start ----------------------------");

$asda_conf = $config->get('asda');

$remember->site_name = 'asda';

$remember->retrieve_data();

if($config->get('env') == 'dev'){
    $logger->notice('Running In Development Environment.');
} else {
    $logger->notice('Running In Live Environment.');
}

try {

    if($asda_conf->run){
        $logger->notice("Asda Scraping Start");
        $asda = new Asda($config,$logger,$database,$remember);
    
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

        if($asda_conf->reviews){
            $asda->reviews();
        }
    
        $logger->notice("Asda Scraping Complete");
    }

} catch(Exception $e){
    //Save failure error
    //Exit Script

    $error_message = $e->getMessage();
    $error_file = $e->getFile();
    $error_stack = $e->getTraceAsString();
    $line_number = $e->getLine();

    $remember->set_error($error_message,$error_file,$e->getTrace(),$line_number);

    $logger->error('Error Occured Exiting Script');
    $logger->error('Message: ' . $error_message);
    $logger->error('File: ' . $error_file);
    $logger->error('Error Stack: ' .$error_stack);

    $remember->save_data();
    
    throw new Exception($e);
}



$logger->notice("---------------------------- Script Complete ----------------------------");

?>