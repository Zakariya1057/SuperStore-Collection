<?php

namespace Collection\Supermarkets\Asda\Services;

use Exception;

use Interfaces\StoreInterface;

use Models\Store\FacilityModel;
use Models\Store\LocationModel;
use Models\Store\OpeningHourModel;
use Models\Store\StoreModel;

use Collection\Supermarkets\Asda\Asda;

class StoreService extends Asda implements StoreInterface {

    public function store_details(?string $site_store_id, $url = null): ?StoreModel {
        return $this->page_store_details($url);
    }

    public function page_store_details($url){
        $response = $this->request_service->request($url);
        $content = $this->request_service->parse_html($response);

        $json_body = $this->request_service->parse_json($content->filter('script#js-map-config-dir-map')->eq(0)->text());

        return $this->parse_store_data($json_body->entities[0]);
    }

    public function parse_store_data($store_item){
        $item_details = $store_item->profile;

        $id = $item_details->meta->id;
        $name = trim(preg_replace('/\s*supermarket|superstore/i','',$item_details->name));

        $store = new StoreModel($this->database_service);

        $description = $item_details->c_aboutSectionDescription ?? NULL;
        $site_image = $item_details->googleCoverPhoto->image->sourceUrl ?? NULL;
        $site_url = $item_details->c_pagesURL;
        $uber_url = $store_item->derivedData->uber->url ?? NULL;
        $google_url = $store_item->listings->googleMyBusiness->url ?? NULL;

        $new_store = $store;
        $new_store->name = $name;
        $new_store->site_store_id = $id;
        $new_store->store_type_id = $this->store_type_id;
        $new_store->description = $description;
        $new_store->store_image = 
        $new_store->url = $site_url;
        $new_store->uber_url = $uber_url;
        $new_store->google_url = $google_url;
        $new_store->store_image = $site_image;

        $this->set_location($store, $item_details);
        $this->set_opening_hours($store, $item_details);
        $this->set_facilities($store, $item_details);

        return $new_store;
    }

    public function set_location(&$store, $location_details){
        $location = new LocationModel($this->database_service);

        $address = $location_details->address;
        $longitude = $latitude = null;

        if(property_exists($location_details, 'displayCoordinate', ) || property_exists($location_details, 'geocodedCoordinate')){
            $coordinates = $location_details->displayCoordinate ?? $location_details->geocodedCoordinate;
            $longitude = $coordinates->long;
            $latitude = $coordinates->lat;
        }

        $city = $address->city;
        $postcode = $address->postalCode;

        $location->longitude = $longitude;
        $location->latitude = $latitude;

        $location->address_line1 = $address->line1;
        $location->address_line2 = $address->line2;
        $location->address_line3 = $address->line3;

        $location->city = $city;
        $location->postcode = $postcode;

        $location->country = $this->store_country;
        
        $store->location = $location;
    }

    public function set_opening_hours(&$store, $hours_details){
        $hours_details = $hours_details->hours;

        $normal_hours = $hours_details->normalHours;

        $hours = [];

        foreach($normal_hours as $day_of_week => $hour_item){

            $opening_hours = new OpeningHourModel($this->database_service);

            $opening_hours->closed_today = $hour_item->isClosed;

            $opening_hours->day_of_week = $day_of_week;

            if(!$opening_hours->closed_today){
                // If Closed, no opens or closed
                $time_details = $hour_item->intervals[0];
                $opening_hours->opens_at = $this->format_time($time_details->start);
                $opening_hours->closes_at = $this->format_time($time_details->end);
            }

            $opening_hours->closed_today = $opening_hours->closed_today ? 1 : 0;
            
            $hours[] = $opening_hours;
        }

        $store->opening_hours = $hours;

    }

    public function set_facilities(&$store, $facilities_details){
        $facilities = [];

        $facility_options = [
            'c_communityRoom' => 'Community Room',
            'c_costaCoffee' => 'Costa Coffee',
            'c_deliCounter' => 'Deli Counter',
            'c_electricalGoods' => 'Electric Goods',
            'c_ethnicFoods' => 'Ethnic Foods',
            'c_expressDiner' => 'Express Diner',
            'c_expressPizzaCounter' => 'Express Pizza Counter',
            'c_expressRotisserie' => 'Express Rotisserie',
            'c_firstChoiceTravel' => 'First Choice Travel',
            'c_fishCounter' => 'Fish Counter',
            'c_groceryClickAndCollect' => 'Click & Collect',
            'c_halal' => 'Halal',
            'c_homeShopping' => 'Home Shopping',
            'c_hotChicken' => 'Hot Chicken',
            'c_instantPhotoPrint' => 'Instant Photo Print',
            'c_opticians' => 'Opticians',
            'c_organic' => 'Organic',
            'c_pharmacy' => 'Pharmacy',
            'c_postOffice' => 'Post Office',
            'c_recycling' => 'Recycling',
        ];
        
        $facilities_list = $facilities_details->c_facilitiesList;
        $unique_facilities = [];
        
        foreach($facility_options as $key => $facility_name){
            if(!key_exists($facility_name, $unique_facilities) && property_exists($facilities_details, $key) && !is_null($facilities_details->{$key})){
                $unique_facilities[$facility_name] = 1;
                $facilities_list[] = $facility_name;
            }
        }

        foreach($facilities_list as $facility_name){
            if (!key_exists($facility_name, $unique_facilities)){
                $unique_facilities[$facility_name] = 1;
                
                $facility = new FacilityModel($this->database_service);
                $facility->name = $facility_name;
                $facilities[] = $facility;
            }
        }

        $store->facilities = $facilities;
    }

    public function format_time($time){
        // 830 -> 08:30:00
        // 2300 -> 23:00:00

        if($time == 0){
            $time = '0000';
        }

        if(is_null($time)){
            throw new Exception('Time Required To Parse');
        }

        $length = strlen($time);

        if($length < 3){
            throw new Exception('Unknown Time Format: '. $time);
        } elseif($length == 3){
            //830 -> 0830
            $time = "0$time";
        }

        preg_match('/^(\d{2})(\d{2})$/',$time,$matches);

        if(!$matches){
            throw new Exception('Unknown Regex Time Format: '. $time);
        } else {
            $hour_part = $matches[1];
            $minute_part = $matches[2];

            $new_time = "$hour_part:$minute_part:00";

            $this->logger->debug("Time Format: $time -> $new_time");
            
            return $new_time;
        }
    }
}

?>