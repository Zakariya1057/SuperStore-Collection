<?php

namespace Monitors;

use Exception;
use Interfaces\StoreInterface;
use Models\Store\FacilitiesModel;
use Models\Store\LocationModel;
use Models\Store\StoreModel;
use Monolog\Logger;
use Services\Config;
use Services\Database;
use Services\Notification;
use Supermarkets\Asda\Stores\Stores;

class MonitorStores {

    public $notification, $config, $logger, $database, $store_model;
    
    public $store_collection;

    function __construct(Config $config, Logger $logger, Database $database, StoreInterface $store_collection){
        $this->config = $config;
        $this->logger = $logger;
        $this->database = $database;

        $this->store_model = new StoreModel($database);

        $this->store_collection = $store_collection;

        $this->notification = new Notification($config, $logger);
    }

    public function monitor_stores($store_type){

        $store_type_id = $store_type->store_type_id;
        $store_type_name = $store_type->name;

        $this->logger->debug('Store Monitoring');

        $stores = $this->store_model->where(['store_type_id' => $store_type_id])
        ->select_raw(['stores.*','TIMESTAMPDIFF(HOUR, `last_checked`, NOW()) as time_difference'])
        ->where_raw(['TIMESTAMPDIFF(HOUR, `last_checked`, NOW()) > 3', 'stores.store_type_id = ' . $store_type_id])
        ->get();

        foreach($stores as $store){

            if(is_null($store)){
                return;
            }

            $this->logger->notice("------- $store_type_name Store Start -----------");
            $this->logger->debug("Last Checked: {$store->time_difference} Hours Ago");
            $this->logger->debug("Updating Store: [{$store->id}] {$store->name}");
            
            $this->check_store_change($store);

            $this->logger->notice("------- $store_type_name Store Complete --------");
        }

    }

    public function check_store_change($store){
        
        $store_id = $store->id;
        
        $new_store = $this->store_collection->store_details($store->site_store_id, $store->url);
        
        if(is_null($new_store)){
            throw new Exception('New Store Not Found: ' . $store_id);
        }


        $this->database->start_transaction();


        // Update hours
        $this->update_hours($store_id, $new_store);

        // Update facilities
        $this->update_facilities($store_id, $new_store);

        // Update Location
        $this->update_locations($store_id, $new_store);

        // Update details
        $this->update_details($store_id, $new_store);


        $this->database->commit_transaction();

    }

    private function update_details($store_id, $new_store){
        $this->store_model->where(['id' => $store_id])->update([
            'name' => $new_store->name,
            'description' => $new_store->description,
            'last_checked' => date('Y-m-d H:i:s')
        ]);
    }

    private function update_locations($store_id, $new_store){

        $new_location = $new_store->location;

        $location = new LocationModel($this->database);
        $location->where(['store_id' => $store_id])->update([
            'city' => $new_location->city,
            'postcode' => $new_location->postcode,

            'address_line1' => $new_location->address_line1,
            'address_line2' => $new_location->address_line2,
            'address_line3' => $new_location->address_line3,

            'country' => $new_location->country,
            'region' => $new_location->region,

            'latitude' => $new_location->latitude,
            'longitude' => $new_location->longitude,
        ]);

    }

    private function update_facilities($store_id, $new_store){
        $found_facilities = [];

        foreach($new_store->facilities as $facility){
            $facility->insert_ignore = 1;
            $facility->store_id = $store_id;
            $facility->save();

            $store_facility = $facility->where(['store_id' => $store_id, 'name' => $facility->name])->get()[0] ?? null;
            if(!is_null($store_facility)){
                $found_facilities[] = $store_facility->id;
            }
        }

        if(count($found_facilities) > 0){
            $facility = new FacilitiesModel($this->database);
            $facility->where(['store_id' => $store_id])->where_not_in('id', $found_facilities)->delete();
        }
    }

    private function update_hours($store_id, $new_store){

        foreach($new_store->opening_hours as $hour){
            $hour
            ->where(['store_id' => $store_id, 'day_of_week' => $hour->day_of_week])
            ->update(['opens_at' => $hour->opens_at, 'closes_at' => $hour->closes_at, 'closed_today' => $hour->closed_today]);
        }

    }

}

?>