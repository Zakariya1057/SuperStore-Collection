<?php

namespace ElasticSearch;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Monolog\Logger;
use Shared\Config;
use Shared\Database;

class Search {
        
    public Client $client;
    public Database $database;
    public Logger $logger;
    public Config $config;

    function __construct(Config $config, Logger $logger, Database $database, Client $client=null){
        $this->config = $config;
        $this->logger = $logger;
        $this->database = $database;

        if(!$client){
            $details = (array)$config->get('elasticsearch.credentials');
            $this->client = ClientBuilder::create()->setRetries(3)->setHosts([$details])->setLogger($logger)->build();
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
        $mapping = new Mapping($this->config, $this->logger, $this->database, $this->client);
        $mapping->map_products();
        $mapping->map_stores();
        $mapping->map_categories();
    }

    /**
     * Indexing all stores, products, categories documents
     *
     * @return void
    */
    public function indexes(){
        $index = new Indices($this->config, $this->logger, $this->database, $this->client);
        $index->index_products();
        $index->index_stores();
        $index->index_categories();
    }
    

}

?>