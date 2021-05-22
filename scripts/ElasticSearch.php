<?php

require_once __DIR__.'/../vendor/autoload.php';

use Search\Search;
use Services\ConfigService;
use Services\DatabaseService;
use Services\LoggerService;

$config_service = new ConfigService();

$index_documents = $config_service->get('elasticsearch.index_documents');
$create_index = $config_service->get('elasticsearch.create_index');

$logger_service = new LoggerService('ElasticSearch');
$logger = $logger_service->logger_handler;

$logger->notice("---------------------------- ElasticSearch Script Start ----------------------------");

$database_service = new DatabaseService($config_service, $logger);

$search = new Search($config_service, $logger, $database_service);

if($create_index){
    $search->mappings();
}

if($index_documents){
    $search->indexes();
}

$logger->notice("---------------------------- ElasticSearch Script END ------------------------------");

?>