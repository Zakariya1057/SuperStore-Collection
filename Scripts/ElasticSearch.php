<?php

require_once __DIR__.'/../vendor/autoload.php';

use ElasticSearch\Search;
use Shared\Config;
use Shared\Loggers;
use Shared\Database;

$config = new Config();
$details = (array)$config->get('elasticsearch.credentials');

$index_documents = $config->get('elasticsearch.index_documents');
$create_index = $config->get('elasticsearch.create_index');

$log = new Loggers();
$logger = $log->logger_handler;

$database = new Database($config,$logger);

$search = new Search($config, $logger, $database);

if($create_index){
    $search->mappings();
}

if($index_documents){
    $search->indexes();
}

?>