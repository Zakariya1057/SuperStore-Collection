<?php

namespace Search;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Monolog\Logger;
use Services\ConfigService;
use Services\DatabaseService;

class Search {
        
    public $client;
    public $config_service, $logger, $database_service;

    function __construct(ConfigService $config_service, Logger $logger, DatabaseService $database_service, Client $client=null){
        $this->config_service = $config_service;
        $this->logger = $logger;
        $this->database_service = $database_service;

        if(!$client){
            $elasticsearch = $config_service->get('elasticsearch.hosts');

            $elasticsearch_config = $elasticsearch->{ $elasticsearch->env };
            $host = $elasticsearch_config->host;

            $this->client = ClientBuilder::create()->setRetries(3)->setHosts([ $host ])->build();
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
        $mapping->map_supermarket_chains();
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
        $index->index_supermarket_chains();
        $index->index_promotions();
        $index->index_categories();
    }
    

}

?>