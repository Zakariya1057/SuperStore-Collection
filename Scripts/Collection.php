<?php

ini_set('memory_limit', '-1');

require_once __DIR__.'/../vendor/autoload.php';

use Services\Config;
use Services\Loggers;
use Services\Database;
use Services\Remember;

use Supermarkets\Asda\Asda;
use Supermarkets\Canadian_Superstore\CanadianSuperstore;

$config = new Config();
$log = new Loggers();

$logger = $log->logger_handler;

$database = new Database($config,$logger);

$remember = new Remember($config,$logger,$database);

$logger->notice("---------------------------- Collection Script Start ----------------------------");


if($config->get('env') == 'dev'){
    $logger->notice('Running In Development Environment.');
} else {
    $logger->notice('Running In Live Environment.');
}

try {

    $asda_conf = $config->get('stores.asda');

    if($asda_conf->run){

        $remember->store_type_id = $asda_conf->store_type_id;
        $remember->retrieve_data();

        $logger->notice("----------  Asda Scraping Start ----------");
        
        $asda = new Asda($config,$logger,$database,$remember);
    
        // Generate Asda Store Type
        $asda->store_type();

        if($asda_conf->stores){
            //Get all stores in given city
            $asda->stores();
        }
    
        if($asda_conf->groceries){
            //Get all product sold on site
            $asda->groceries();
        }
    
        if($asda_conf->recommended){
            //Get all similar Products.
            $asda->recommended();
        }

        if($asda_conf->reviews){
            $asda->reviews();
        }

        $logger->notice("---------- Asda Scraping Complete ---------- ");
    }

    $asda_conf = $config->get('stores.canadian_superstore');

    if($asda_conf->run){

        $remember->store_type_id = $asda_conf->store_type_id;
        $remember->retrieve_data();

        $logger->notice("----------  Real Canadian Superstore Scraping Start ----------");
        
        $canadian_superstore = new CanadianSuperstore($config,$logger,$database,$remember);

        $logger->notice("---------- Real Canadian Superstore Scraping Complete ---------- ");
    }

} catch(Exception $e){
    $error_message = $e->getMessage();
    $error_file = $e->getFile() ?? null;
    $error_stack = $e->getTraceAsString() ?? null;

    $remember->set_error($error_message,$error_file,$error_stack,null);

    $logger->error('Error Occured Exiting Script');
    $logger->error('Message: ' . $error_message);
    $logger->error('File: ' . $error_file);
    $logger->error('Error Stack: ' .$error_stack);

    $remember->save_data();
    
    throw new Exception($e);
}

$logger->notice("---------------------------- Collection Script Complete ----------------------------");

?>