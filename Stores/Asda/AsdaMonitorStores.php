<?php

namespace Stores\Asda;

use Exception;
use Models\Store\StoreModel;

class AsdaMonitorStores extends Asda {

    public $store;

    function __construct($config,$logger,$database){
        parent::__construct($config,$logger,$database,null);
        $this->store = new StoreModel($database);
    }

    public function monitor_stores(){
        // Check store details
        // Store Hours, Facilities, etc

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
            $this->logger->debug("Store: [{$store->id}] {$store->name}");
            $this->check_store_change($store);
            $this->logger->notice("------- Asda Store Complete --------");
        }

    }

    public function check_store_change($store){
        $asda_store = new AsdaStores($this->config, $this->logger, $this->database, $this->remember);
        
        $new_store = $asda_store->page_store_details($store->url);

        // Check opening hours
        // Check facilities
        // Store Image, description, image, google_url, image ulr

        $product_changed = false;

        $monitor_fields = ['name', 'description', 'store_image'];
        // Decode fields
        foreach($monitor_fields as $field){
            if($store->{$field} != $new_store->{$field}){
                $this->logger->notice("Store $field changed: {$store->{$field}} -> {$new_store->{$field}}");
                $product_changed = true;
            }
        }

        if($product_changed){
            $this->logger->notice('Store Details Changed');
        }

    }

}

?>