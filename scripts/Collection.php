<?php

ini_set('memory_limit', '-1');

require_once __DIR__.'/../vendor/autoload.php';

use Services\CLIService;
use Services\ConfigService;
use Services\LoggerService;
use Services\DatabaseService;
use Services\RememberService;

use Collection\Supermarkets\Asda\Asda;
use Collection\Supermarkets\Canadian_Superstore\CanadianSuperstore;

$cli = new CLIService();
$cli->run();
$store_type = $cli->get_store_type();

$config_service = new ConfigService();
$logger_service = new LoggerService('Collection', $store_type);

$logger = $logger_service->logger_handler;

$database_service = new DatabaseService($config_service, $logger);

$remember_service = new RememberService($config_service, $logger, $database_service);

$logger->notice("---------------------------- Collection Script Start ----------------------------");

if($config_service->get('env') == 'dev'){
    $logger->notice('Running In Development Environment.');
} else {
    $logger->notice('Running In Live Environment.');
}

try {

    $asda_conf = $config_service->get('stores.asda');

    if(strtolower($store_type) == 'asda'){

        if($asda_conf->run){

            $remember_service->store_type_id = $asda_conf->store_type_id;
            $remember_service->retrieve_data();
    
            $logger->notice("----------  Asda Scraping Start ----------");
            
            $asda = new Asda($config_service, $logger, $database_service, $remember_service);
        
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

    } else {

        $canadian_superstore_conf = $config_service->get('stores.real_canadian_superstore');

        if($canadian_superstore_conf->run){
    
            $remember_service->store_type_id = $canadian_superstore_conf->store_type_id;
            $remember_service->retrieve_data();
    
            $logger->notice("----------  Real Canadian Superstore Scraping Start ----------");
    
            $canadian_superstore = new CanadianSuperstore($config_service,$logger,$database_service,$remember_service);
    
            $canadian_superstore->store_type();
    
            if($canadian_superstore_conf->stores){
                $canadian_superstore->stores();
            }
    
            if($canadian_superstore_conf->groceries){
                $canadian_superstore->groceries();
            }
            
            if($canadian_superstore_conf->recommended){
                $canadian_superstore->recommended();
            }
    
            $logger->notice("---------- Real Canadian Superstore Scraping Complete ---------- ");
        }

    }
} catch(Exception $e){
    $error_message = $e->getMessage();
    $error_file = $e->getFile() ?? null;
    $error_stack = $e->getTraceAsString() ?? null;

    $logger->error('Error Occured Exiting Script');
    $logger->error('Message: ' . $error_message);
    $logger->error('File: ' . $error_file);
    $logger->error('Error Stack: ' .$error_stack);
    
    throw new Exception($e);
}

$logger->notice("---------------------------- Collection Script Complete ----------------------------");

?>