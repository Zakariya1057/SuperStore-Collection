<?php

namespace Search;

use Elasticsearch\Client;
use Monolog\Logger;
use Shared\Config;
use Shared\Database;

// Map Documents
class Mapping extends Search {
        
    function __construct(Config $config, Logger $logger, Database $database, Client $client){
        parent::__construct($config, $logger, $database, $client);
    }

    public function map_products(){
        $params = $this->create_params('products');
        $response = $this->client->indices()->create($params);
    }

    public function map_stores(){
        $params = $this->create_params('stores');
        $response = $this->client->indices()->create($params);
    }

    public function map_categories(){
        $params = $this->create_params('categories');
        $response = $this->client->indices()->create($params);
    }

    private function create_params($index): Array {

        $this->logger->debug('Creating Index For: '. $index);
        // $this->client->indices()->delete(['index' => $index]);

        $mappings_properties = $this->config->get('elasticsearch.indices.'.$index);
        $mapping_settings = $this->config->get('elasticsearch.settings');

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