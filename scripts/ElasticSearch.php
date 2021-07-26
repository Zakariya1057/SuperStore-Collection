<?php

require_once __DIR__.'/../vendor/autoload.php';

use Search\Search;
use Services\ConfigService;
use Services\DatabaseService;
use Services\LoggerService;

$config_service = new ConfigService();

$elasticsearch = $config_service->get('elasticsearch.hosts');
$env = $elasticsearch->env;
$elasticsearch_config = $elasticsearch->{$env};

$logger_service = new LoggerService('ElasticSearch');
$logger = $logger_service->logger_handler;

$logger->debug('ElasticSearch Environment: ' . ($env == 'prod' ? 'Production' : 'Development'));

$logger->notice("---------------------------- ElasticSearch Script Start ----------------------------");

$database_service = new DatabaseService($config_service, $logger);

$search = new Search($config_service, $logger, $database_service);

if($elasticsearch_config->create_index){
    $search->mappings();
}

if($elasticsearch_config->index_documents){
    $search->indexes();
}

$logger->notice("---------------------------- ElasticSearch Script END ------------------------------");

?>