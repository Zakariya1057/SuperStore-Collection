<?php

ini_set('memory_limit', '-1');

require_once __DIR__.'/../vendor/autoload.php';

use Services\CLIService;
use Services\ConfigService;
use Services\LoggerService;
use Services\DatabaseService;

use Collection\Loblaws\Loblaws;

$cli = new CLIService();
$cli->run();
$store_type = $cli->get_store_type();

$config_service = new ConfigService();
$logger_service = new LoggerService('Collection', $store_type);

$logger = $logger_service->logger_handler;

$database_service = new DatabaseService($config_service, $logger);

$logger->notice("---------------------------- Collection Script Start ----------------------------");

if($config_service->get('env') == 'dev'){
    $logger->notice('Running In Development Environment.');
} else {
    $logger->notice('Running In Live Environment.');
}

try {

    // Run once for the company. Create shared categories for supermarket_chains. Get each product price. One script only.

    $loblaws_settings = $config_service->get('companies.loblaws.settings');

    if($loblaws_settings->run){

        $logger->notice("----------  Real Canadian Superstore Scraping Start ----------");

        $canadian_superstore = new Loblaws($config_service, $logger, $database_service);

        if($loblaws_settings->stores){
            $canadian_superstore->stores();
        }

        if($loblaws_settings->groceries){
            $canadian_superstore->groceries();
        }
        
        if($loblaws_settings->recommended){
            $canadian_superstore->recommended();
        }

        $logger->notice("---------- Real Canadian Superstore Scraping Complete ---------- ");
    
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