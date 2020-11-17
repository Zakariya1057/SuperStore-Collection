<?php

namespace Stores\Asda;

use Exception;
use Models\Store\FacilitiesModel;
use Models\Store\StoreModel;

class AsdaMonitorStores extends Asda {

    public $store;

    function __construct($config,$logger,$database){
        parent::__construct($config,$logger,$database,null);
        $this->store = new StoreModel($database);
    }

    public function monitor_stores(){

        $this->logger->debug('Store Monitoring');

        $stores = $this->store->select_raw(['stores.*','TIMESTAMPDIFF(HOUR, `last_checked`, NOW()) as time_difference'])
        // ->where_raw(['TIMESTAMPDIFF(HOUR, `last_checked`, NOW()) > 3'])
        ->get();

        // fix image saving everytime to once a day
        foreach($stores as $store){

            if(is_null($store)){
                return;
            }

            $this->logger->notice("------- Asda Store Start -----------");
            $this->logger->debug("Last Checked: {$store->time_difference} Hours Ago");
            $this->logger->debug("Updating Store: [{$store->id}] {$store->name}");
            $this->check_store_change($store);
            $this->logger->notice("------- Asda Store Complete --------");
        }

    }

    public function check_store_change($store){
        $asda_store = new AsdaStores($this->config, $this->logger, $this->database, $this->remember);
        
        $new_store = $asda_store->page_store_details($store->url);

        $this->database->start_transaction();

        // Update hours
        foreach($new_store->opening_hours as $hour){
            $hour
            ->where(['store_id' => $store->id, 'day_of_week' => $hour->day_of_week])
            ->update(['opens_at' => $hour->opens_at, 'closes_at' => $hour->closes_at, 'closed_today' => $hour->closed_today]);
        }

        // Update facilities
        $found_facilities = [];

        foreach($new_store->facilities as $facility){
            // Delete all facilities not found here outside.
            $facility->insert_ignore = 1;
            $facility->store_id = $store->id;
            $facility->save();

            $store_facility = $facility->where(['store_id' => $store->id, 'name' => $facility->name])->get()[0] ?? null;
            if($store_facility){
                $found_facilities[] = $store_facility->id;
            }
        }

        if(count($found_facilities) > 0){
            $facility = new FacilitiesModel($this->database);
            $facility->where_not_in('id', $found_facilities)->delete();
        }

        // Update details
        $this->store->where(['id' => $store->id])->update([
            'name' => $new_store->name,
            'description' => $new_store->description,
            'last_checked' => date('Y-m-d H:i:s')
        ]);

        $this->database->commit_transaction();

    }

}

?>