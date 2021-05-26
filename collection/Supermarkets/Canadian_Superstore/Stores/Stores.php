<?php

namespace Collection\Supermarkets\Canadian_Superstore\Stores;

use Collection\Services\SharedFlyerService;
use Collection\Services\SharedRegionService;
use Collection\Services\SharedStoreService;
use Collection\Supermarkets\Canadian_Superstore\CanadianSuperstore;
use Collection\Supermarkets\Canadian_Superstore\Services\FlyerService;
use Collection\Supermarkets\Canadian_Superstore\Services\StoreService;

class Stores extends CanadianSuperstore {

    private $shared_store_service;
    private $store_service;
    private $flyer_service;
    private $region_service;

    private function setupStoreServices(){
        if(is_null($this->store_service) || is_null($this->shared_store_service)){
            $this->store_service = new StoreService($this->config_service, $this->logger, $this->database_service);
            $this->shared_store_service = new SharedStoreService($this->database_service);
            $this->flyer_service = new FlyerService($this->config_service, $this->logger, $this->database_service);

            $this->region_service = new SharedRegionService($this->database_service);
        }
    }

    public function create_stores(){
        $this->setupStoreServices();

        $this->logger->notice($this->store_name . ' Finding All Stores');

        if($this->env == 'dev'){
            $stores_response = file_get_contents(__DIR__.'/../../../data/Canadian_Superstore/Stores.json');
        } else {
            $stores_response = $this->request_service->request($this->endpoints->stores . '?bannerIds=superstore');
        }

        $stores_data = $this->request_service->parse_json($stores_response);

        $count = count($stores_data);

        $this->logger->notice($count . ' Stores Found');

        foreach($stores_data as $store_data){
            if(!$store_data->visible){
                continue;
            }

            if($store_data->address->region != 'Yukon'){
                continue;
            }

            $site_store_id = $store_data->id;
            $name = $store_data->name;

            $this->logger->notice("Store: [$site_store_id] $name");

            if(!$this->shared_store_service->store_exists($site_store_id, $this->store_type_id)){
                $this->database_service->start_transaction();

                $this->logger->debug('New Store Not Found In Database. Creating');
                $store = $this->store_service->store_details($site_store_id);

                if(is_null($store)){
                    $this->logger->error('No Product Details Found. Not Saving Anything');
                } else {

                    $region_id = $this->region_service->create_region($store->region);

                    $store_id = $this->shared_store_service->create_store($store, $region_id);

                    $flyers = $this->flyer_service->get_flyers($site_store_id, $store_id);
                    $this->shared_store_service->create_flyers($flyers, $store_id);
                }

                $this->database_service->commit_transaction();
            } else {
                $this->logger->error('Store Found In Database. Skipping: ' . $site_store_id);
            }

        }
    }
}

?>