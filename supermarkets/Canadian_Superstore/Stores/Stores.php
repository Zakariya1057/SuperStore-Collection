<?php

namespace Supermarkets\Canadian_Superstore\Stores;

use Exception;
use Interfaces\StoreInterface;
use Models\Store\FacilitiesModel;
use Models\Store\LocationModel;
use Models\Store\OpeningHoursModel;
use Models\Store\StoreModel;
use Supermarkets\Canadian_Superstore\CanadianSuperstore;

class Stores extends CanadianSuperstore implements StoreInterface {

    public function create_stores(){
        
        $store_model = new StoreModel($this->database);

        
        $this->logger->notice($this->store_name . " Finding All Stores");

        if($this->env == 'dev'){
            $stores_response = file_get_contents(__DIR__."/../../../data/Canadian_Superstore/Stores.json");
        } else {
            $stores_response = $this->request->request($this->endpoints->stores . '?bannerIds=superstore');
        }

        $stores_data = $this->request->parse_json($stores_response);

        $count = count($stores_data);

        $this->logger->notice($count . ' Stores Found');

        foreach($stores_data as $store_data){
            
            $site_store_id = $store_data->id;
            $name = $store_data->name;
            $banner_id = strtolower($store_data->storeBannerId);
            $pickupType = strtolower($store_data->pickupType);
            $visible = $store_data->visible;

            $this->logger->notice("Store: [$site_store_id] $name");

            if(!is_numeric($site_store_id)){
                // $this->logger->error('Site Store ID Not Number: ' . $site_store_id);
                continue;
            }

            if($banner_id != 'superstore'){
                // $this->logger->error('Incorrect Site Banner: ' . $banner_id);
                continue;
            }

            if(!$visible){
                // $this->logger->error('Ignoring Store Invisible: ' . $banner_id);
                continue;
            }

            if($pickupType != 'store'){
                // $this->logger->error('Incorrect Pickup Type: ' . $pickupType);
                continue;
            }

            
            $store_results = $store_model->where(['site_store_id' => $site_store_id])->get()[0] ?? null;

            if(!is_null($store_results)){
                $this->logger->error('Store Found In Database. Skipping: ' . $site_store_id);
                continue;
            }

            $store = $this->store_details($store_data->id);
            $store_id = $store->save();

            $this->create_location($store->location, $store_id);
            $this->create_hours($store->opening_hours, $store_id);
            $this->create_facilitites($store->facilities, $store_id);
        }
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

    public function store_details($site_store_id, $url = null): ?StoreModel {

        $this->logger->notice('-- Start Store Details: '. $site_store_id);

        $store = new StoreModel($this->database);

        if($this->env == 'dev'){
            $store_response = file_get_contents(__DIR__."/../../data/Canadian_Superstore/Store.json");
        } else {
            $url = $this->endpoints->stores . '/' . $site_store_id;
            $store_response = $this->request->request($url, 'GET', [], ['Site-Banner' => 'superstore']);
        }

        $store_data = $this->request->parse_json($store_response);

        if(is_null($store_data)){
            $this->logger->error('Leaving No Store Details Found: ' . $site_store_id);
            return null;
        }

        $store_details = $store_data->storeDetails;

        $store->store_type_id = $this->store_type_id;
        $store->site_store_id = $site_store_id;
        $store->url = 'https://www.realcanadiansuperstore.ca/store-locator/details/' . $site_store_id;

        $store->name = $store_data->name;
        $store->manager = $store_details->manager;
        $store->telephone = $store_details->phoneNumber;

        $this->set_location($store, $store_data);
        $this->set_hours($store, $store_details->storeHours);
        $this->set_facilities($store, $store_details->departments);

        $this->logger->notice('-- Complete Store Details: '. $site_store_id);

        return $store;
    }

    private function set_location($store, $store_data){
        $location_data = $store_data->address;
        $geo_data = $store_data->geoPoint;

        $location = new LocationModel($this->database);

        $location->postcode = $location_data->postalCode;

        $location->address_line1 = $location_data->line1;
        $location->address_line2 = $location_data->line2 == "" ? null : $location_data->line2;

        $location->city = $location_data->town;
        $location->region = $location_data->region;
        $location->country = $location_data->country;

        $location->longitude = $geo_data->longitude;
        $location->latitude = $geo_data->latitude;

        $store->location = $location;
    }

    private function set_hours($store, $store_hours){

        $store->opening_hours = [];

        foreach($store_hours as $index => $store_hour){

            $hour = new OpeningHoursModel($this->database);

            $hour->day_of_week = $index;

            $time_matches = explode(' - ', $store_hour->hours);

            if($time_matches && count($time_matches) == 2){
                $opens_at = date("H:i:s", strtotime($time_matches[0]));
                $closes_at =  date("H:i:s", strtotime($time_matches[1]));
                
                $hour->opens_at = $opens_at;
                $hour->closes_at = $closes_at;
                $hour->closed_today = 0;
            } else {
                $hour->closed_today = 1;
            }

            $store->opening_hours[] = $hour;
        }

    }

    private function set_facilities($store, $store_facilities){
        $store->facilities = [];

        foreach($store_facilities as $facility_item){
            $facility = new FacilitiesModel($this->database);
            $facility->name = $facility_item->name;
            $store->facilities[] = $facility;
        }
    }

}

?>