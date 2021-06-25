<?php

namespace Monitors;

use Exception;

use Collection\Supermarkets\Canadian_Superstore\Services\FlyerService;

use Monolog\Logger;

use Interfaces\StoreInterface;
use Models\Store\FacilityModel;
use Models\Flyer\FlyerModel;
use Models\Store\LocationModel;
use Models\Store\StoreModel;
use Models\Store\OpeningHourModel;

use Collection\Services\SharedFlyerService;
use Collection\Services\SharedStoreService;
use Services\ConfigService;
use Services\DatabaseService;
use Services\NotificationService;

class MonitorStores {

    public $notification, $config_service, $logger, $database_service;
    
    public $store_service, $flyer_service, $shared_store_service;

    private $store_model, $opening_hour, $location_model, $facility_model;

    function __construct(ConfigService $config_service, Logger $logger, DatabaseService $database_service, StoreInterface $store_service){
        $this->config_service = $config_service;
        $this->logger = $logger;
        $this->database_service = $database_service;

        $this->store_service = $store_service;
        
        $this->flyer_service = new FlyerService($config_service, $logger, $database_service);
        $this->shared_flyer_service = new SharedFlyerService($config_service, $logger, $database_service);

        $this->shared_store_service = new SharedStoreService($database_service);

        $this->notification_service = new NotificationService($config_service, $logger);

        $this->store_model = new StoreModel($database_service);
        $this->opening_hour = new OpeningHourModel($database_service);
        $this->location_model = new LocationModel($database_service);
        $this->facility_model = new FacilityModel($database_service);
        $this->flyer_model = new FlyerModel($database_service);
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
        
        $new_store = $this->store_service->store_details($store->site_store_id, $store->url);
        
        if(is_null($new_store)){
            throw new Exception('New Store Not Found: ' . $store_id);
        }

        $this->database_service->start_transaction();

        // Update flyers
        $this->update_flyers($store_id, $new_store);
        
        // Update hours
        $this->update_hours($store_id, $new_store);

        // Update facilities
        $this->update_facilities($store_id, $new_store);

        // Update Location
        $this->update_locations($store_id, $new_store);

        // Update details
        $this->update_details($store_id, $new_store);

        $this->database_service->commit_transaction();
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

        $this->location_model->where(['store_id' => $store_id])->update([
            'city' => $new_location->city,
            'postcode' => $new_location->postcode,

            'address_line1' => $new_location->address_line1,
            'address_line2' => $new_location->address_line2,
            'address_line3' => $new_location->address_line3,

            'country' => $new_location->country,

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

            $store_facility = $this->facility_model->where(['store_id' => $store_id, 'name' => $facility->name])->first();
            if(!is_null($store_facility)){
                $found_facilities[] = $store_facility->id;
            }
        }

        if(count($found_facilities) > 0){
            $this->facility_model->where(['store_id' => $store_id])->where_not_in('id', $found_facilities)->delete();
        }
    }

    private function update_hours($store_id, $new_store){
        foreach($new_store->opening_hours as $hour){
            $this->opening_hour
            ->where(['store_id' => $store_id, 'day_of_week' => $hour->day_of_week])
            ->update(['opens_at' => $hour->opens_at, 'closes_at' => $hour->closes_at, 'closed_today' => $hour->closed_today]);
        }
    }

    private function update_flyers($store_id, $new_store){
        // Delete All Expired Flyers.
        // Save All New Flyers

        if($new_store->store_type_id != 1){
            $this->shared_flyer_service->delete_flyers($store_id);
            $flyers = $this->flyer_service->get_flyers($new_store->site_store_id, $store_id);
            $this->shared_flyer_service->create_flyers($flyers, $store_id);
        }
       
    }
}

?>