<?php

namespace Collection\Services;

use Models\Store\StoreModel;
use Services\DatabaseService;

class SharedStoreService {

    private $store_model;
    private $region_service;

    public function __construct(DatabaseService $database_service){
        $this->store_model = new StoreModel($database_service);
        $this->region_service = new SharedRegionService($database_service);
    }

    public function store_exists(string $site_store_id, int $store_type_id){
        $store_results = $this->store_model->where(['store_type_id' => $store_type_id, 'site_store_id' => $site_store_id])->get()[0] ?? null;

        if(!is_null($store_results)){
            return $store_results->id;
        } else {
            return null;
        }
    }

    public function url_store_exists(string $url){
        $store_results = $this->store_model->where(['url' => $url])->get()[0] ?? null;

        if(!is_null($store_results)){
            return $store_results->id;
        } else {
            return null;
        }
    }

    public function create_store(StoreModel $store){
        $store_id = $store->save();

        $region_id = $this->region_service->create_region($store->region);
        
        $this->create_location($store->location, $store_id, $region_id);
        $this->create_hours($store->opening_hours, $store_id);
        $this->create_facilitites($store->facilities, $store_id);

       
        return $store_id;
    }

    public function create_flyers($flyers, $store_id){
        foreach($flyers as $flyer){
            $flyer->store_id = $store_id;
            $flyer->save();
        }
    }

    private function create_location($location, $store_id, $region_id){
        $location->store_id = $store_id;
        $location->region_id = $region_id;
        $location->save();
    }

    private function create_hours($hours, $store_id){
        foreach($hours as $hour){
            $hour->store_id = $store_id;
            $hour->save();
        }
    }

    private function create_facilitites($facilitites, $store_id){
        foreach($facilitites as $facility){
            $facility->store_id = $store_id;
            $facility->save();
        }
    }

}

?>