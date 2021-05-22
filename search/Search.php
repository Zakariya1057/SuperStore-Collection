<?php

namespace Search;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Monolog\Logger;
use Services\ConfigService;
use Services\DatabaseService;

class Search {
        
    public $client;
    public $database_service;
    public $logger;
    public $config_service;

    function __construct(ConfigService $config_service, Logger $logger, DatabaseService $database_service, Client $client=null){
        $this->config_service = $config_service;
        $this->logger = $logger;
        $this->database_service = $database_service;

        if(!$client){
            $host = $config_service->get('elasticsearch.host');
            $this->client = ClientBuilder::create()->setRetries(3)->setHosts([$host])->build();
        } else {
            $this->client = $client;
        }

    }

    /**
     * Mapping all stores, products, categories indexes
     *
     * @return void
    */
    public function mappings(){
        $mapping = new Mapping($this->config_service, $this->logger, $this->database_service, $this->client);
        $mapping->map_products();
        $mapping->map_stores();
        $mapping->map_promotions();
        $mapping->map_categories();
    }

    /**
     * Indexing all stores, products, categories documents
     *
     * @return void
    */
    public function indexes(){
        $index = new Indices($this->config_service, $this->logger, $this->database_service, $this->client);
        $index->index_products();
        $index->index_stores();
        $index->index_promotions();
        $index->index_categories();
    }
    

}

?>