<?php

namespace Collection\Supermarkets\Canadian_Superstore\Services;

use Exception;
use Collection\Supermarkets\Canadian_Superstore\CanadianSuperstore;
use Models\Store\FacilitiesModel;
use Models\Store\LocationModel;
use Models\Store\OpeningHoursModel;
use Models\Store\StoreModel;

class StoreService extends CanadianSuperstore {
    
    public function store_details(string $site_store_id, $url = null): ?StoreModel {
        
        $this->logger->notice('-- Start Store Details: '. $site_store_id);

        $store = new StoreModel($this->database_service);

        if($this->env == 'dev'){
            $store_response = file_get_contents(__DIR__.'/../../data/Canadian_Superstore/Store.json');
        } else {
            $url = $this->endpoints->stores . '/' . $site_store_id;
            $store_response = $this->request_service->request($url, 'GET', [], ['Site-Banner' => 'superstore']);
        }

        $store_data = $this->request_service->parse_json($store_response);

        if(is_null($store_data) || !property_exists($store_data, 'storeDetails')){
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

        $store->flyers = [];

        $this->set_location($store, $store_data);
        $this->set_hours($store, $store_details->storeHours);
        $this->set_facilities($store, $store_details->departments);

        $this->logger->notice('-- Complete Store Details: '. $site_store_id);

        return $store;
    }

    private function set_location($store, $store_data){
        $location_data = $store_data->address;
        $geo_data = $store_data->geoPoint;

        $location = new LocationModel($this->database_service);

        $location->postcode = $location_data->postalCode;

        $location->address_line1 = $location_data->line1;
        $location->address_line2 = $location_data->line2 == '' ? null : $location_data->line2;

        $location->city = $location_data->town;
        $location->region = $location_data->region;
        $location->country = $location_data->country;
        
        $longitude = $geo_data->longitude;
        $latitude = $geo_data->latitude;

        if( (!is_null($longitude) && $longitude != 0) && (!is_null($latitude) && $latitude != 0) ){
            $location->longitude = $longitude;
            $location->latitude = $latitude;
        }

        $store->location = $location;
    }

    private function set_hours($store, $store_hours){

        $store->opening_hours = [];

        foreach($store_hours as $index => $store_hour){

            $hour = new OpeningHoursModel($this->database_service);

            $hour->day_of_week = $index;

            $time_matches = explode(' - ', $store_hour->hours);

            if($time_matches && count($time_matches) == 2){
                $opens_at = date('H:i:s', strtotime($time_matches[0]));
                $closes_at = date('H:i:s', strtotime($time_matches[1]));
                
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
            $facility = new FacilitiesModel($this->database_service);
            $facility->name = $facility_item->name;
            $store->facilities[] = $facility;
        }
    }
}

?>