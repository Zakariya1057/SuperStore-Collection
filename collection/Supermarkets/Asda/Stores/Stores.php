<?php

namespace Collection\Supermarkets\Asda\Stores;

use Collection\Services\SharedStoreService;
use Collection\Supermarkets\Asda\Asda;
use Collection\Supermarkets\Asda\Services\StoreService;
use Symfony\Component\DomCrawler\Crawler;

class Stores extends Asda {

    private $store_service, $shared_store_service;

    private function setupServices(){
        if(is_null($this->store_service)){
            $this->store_service = new StoreService($this->config_service, $this->logger, $this->database_service);
            $this->shared_store_service = new SharedStoreService($this->database_service);
        }
    }

    public function stores(){
        
        $this->setupServices();

        $this->logger->notice("Collecting All Stores.");

        if($this->env == 'dev'){
            $stores_response = file_get_contents(__DIR__."/../../data/Asda/Stores.json");
        } else {
            $stores_response = $this->request_service->request($this->endpoints->stores, 'GET');
        }

        $county_results = $this->request_service->parse_html($stores_response);

        $county_results->filter('a.Directory-listLink')->each(function(Crawler $node, $i){
            $url = 'https://storelocator.asda.com/' . $node->attr('href');
            $name = $node->filter('span')->text();

            $this->logger->debug("County $name - $url");

            $stores_response = $this->request_service->request($url, 'GET');
            $area_results = $this->request_service->parse_html($stores_response);

            $area_results->filter('a.Directory-listLink')->each(function(Crawler $node, $i){
                $url = 'https://storelocator.asda.com/' . $node->attr('href');
                $name = $node->filter('span')->text();
    
                $this->logger->debug("- Area $name - $url");


                $stores_response = $this->request_service->request($url, 'GET');
                $store_results = $this->request_service->parse_html($stores_response);

                $store_results->filter('a.Teaser-titleLink')->each(function(Crawler $node, $i){
                    $url = 'https://storelocator.asda.com/' . str_replace('../', '', $node->attr('href'));
                    $name = $node->filter('span.LocationName-geo')->text();
        
                    $this->logger->debug("-- Store $name - $url");

                    if(!$this->shared_store_service->url_store_exists($url)){
                        $this->database_service->start_transaction();
        
                        $this->logger->debug('New Store Not Found In Database. Creating');
                        $store = $this->store_service->store_details(null, $url);
        
                        if(is_null($store)){
                            $this->logger->error('No Product Details Found. Not Saving Anything');
                        } else {
                            $this->shared_store_service->create_store($store);
                        }
        
                        $this->database_service->commit_transaction();
                    } else {
                        $this->logger->error('Store Found In Database. Skipping: ' . $url);
                    }

                });
            });

        });

    }
    
}

?>