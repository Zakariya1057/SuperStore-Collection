<?php

namespace Collection\Services;

use Models\Store\StoreModel;
use Services\DatabaseService;

class SharedStoreService {

    private $store_model;

    public function __construct(DatabaseService $database_service){
        $this->store_model = new StoreModel($database_service);
    }

    public function store_exists(string $site_store_id, int $store_type_id){
        $store_results = $this->store_model->where(['store_type_id' => $store_type_id, 'site_store_id' => $site_store_id])->get()[0] ?? null;

        if(!is_null($store_results)){
            return $store_results->id;
        } else {
            return null;
        }
    }

    public function create_store(StoreModel $store){
        $store_id = $store->save();

        $this->create_location($store->location, $store_id);
        $this->create_hours($store->opening_hours, $store_id);
        $this->create_facilitites($store->facilities, $store_id);
    }

    private function create_location($location, $store_id){
        $location->store_id = $store_id;
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