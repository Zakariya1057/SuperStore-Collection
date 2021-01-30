<?php

require_once __DIR__.'/../vendor/autoload.php';

use Search\Search;
use Shared\Config;
use Shared\Loggers;
use Shared\Database;

$config = new Config();

$index_documents = $config->get('elasticsearch.index_documents');
$create_index = $config->get('elasticsearch.create_index');

$log = new Loggers();
$logger = $log->logger_handler;

$logger->notice("---------------------------- ElasticSearch Script Start ----------------------------");

$database = new Database($config,$logger);

$search = new Search($config, $logger, $database);

if($create_index){
    $search->mappings();
}

if($index_documents){
    $search->indexes();
}

$logger->notice("---------------------------- ElasticSearch Script END ------------------------------");

?>