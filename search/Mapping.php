<?php

namespace Search;

use Elasticsearch\Client;
use Monolog\Logger;
use Services\ConfigService;
use Services\DatabaseService;

// Map Documents
class Mapping extends Search {
        
    function __construct(ConfigService $config_service, Logger $logger, DatabaseService $database_service, Client $client){
        parent::__construct($config_service, $logger, $database_service, $client);
    }

    public function map_products(){
        $params = $this->create_params('products');
        $response = $this->client->indices()->create($params);
    }

    public function map_supermarket_chains(){
        $params = $this->create_params('supermarket_chains');
        $response = $this->client->indices()->create($params);
    }

    public function map_promotions(){
        $params = $this->create_params('promotions');
        $response = $this->client->indices()->create($params);
    }

    public function map_categories(){
        $params = $this->create_params('categories');
        $response = $this->client->indices()->create($params);
    }

    private function create_params($index): Array {

        $this->logger->debug('Creating Index For: '. $index);
        // $this->client->indices()->delete(['index' => $index]);

        $mappings_properties = $this->config_service->get('elasticsearch.indices.'.$index);
        $mapping_settings = $this->config_service->get('elasticsearch.settings');

        $params = [
            'index' => $index,
            'body' => [
                'settings' => $mapping_settings,
                'mappings' => [
                    'properties' => $mappings_properties
                ]
            ]
            
        ];

        return $params;
    }

}

?>