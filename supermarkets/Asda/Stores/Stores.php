<?php

namespace Supermarkets\Asda\Stores;

use Exception;
use Models\Store\FacilitiesModel;
use Models\Store\LocationModel;
use Models\Store\OpeningHoursModel;
use Models\Store\StoreModel;
use Monolog\Logger;
use Services\Config;
use Services\Database;
use Services\Remember;
use Supermarkets\Asda\Asda;

class Stores extends Asda {

    public function stores(){

        foreach($this->endpoints->stores as $store_url ){

            $stores_endpoint = $store_url . $this->city;

            $this->logger->notice("Finding All Stores In: $this->city");
    
            if($this->env == 'dev'){
                $stores_response = file_get_contents(__DIR__."/../../data/Asda/Stores.json");
            } else {
                $stores_response = $this->request->request($stores_endpoint,'GET',[],['accept' => 'application/json']);
            }
    
            $stores_results = $this->request->parse_json($stores_response);
            
            $stores_list = $stores_results->response->entities;
    
            $this->logger->notice('Found ' . count($stores_list) . ' Stores In ' . $this->city);
    
            foreach($stores_list as $item){
                $this->database->start_transaction();
                $this->store_details($item);
                $this->database->commit_transaction();
            }

        }

    }

    public function page_store_details($url){
        // Get Store details from store url
        $response = $this->request->request($url);
        $content = $this->request->parse_html($response);

        $json_body = $this->request->parse_json($content->filter('script#js-map-config-dir-map')->eq(0)->text());

        return $this->store_details($json_body->entities[0], true);
    }

    public function store_details($store_item,$retrieve=false){

        $item_details = $store_item->profile;

        $id = $item_details->meta->id;
        $name = trim(preg_replace('/\s*supermarket|superstore/i','',$item_details->name));

        $store = new StoreModel($this->database);

        $description = $item_details->c_aboutSectionDescription ?? NULL;
        $site_image = $item_details->googleCoverPhoto->image->sourceUrl ?? NULL;
        $site_url = $item_details->c_pagesURL;
        $uber_url = $store_item->derivedData->uber->url ?? NULL;
        $google_url = $store_item->listings->googleMyBusiness->url ?? NULL;

        $store_results = $store->where(['site_store_id' => $id])->get()[0] ?? null;

        if(is_null($store_results) || $retrieve){
            $this->logger->debug("New Store: $name ($id)");

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

            if(!$retrieve){
                $new_store_id = $new_store->save();
            } else {
                $new_store_id = 0;
            }

            $new_store->location = $this->location($new_store_id,$item_details, $retrieve);
            $new_store->opening_hours = $this->opening_hours($new_store_id,$item_details, $retrieve);
            $new_store->facilities = $this->facilities($new_store_id,$item_details, $retrieve);

            return $new_store;
        } else {
            $this->logger->debug("Store Found In Database: $name ($id)");
        }


    }

    public function location($store_id,$location_details, $retrieve){

        $location = new LocationModel($this->database);

        $store_results = $location->where(['store_id' => $store_id])->get()[0] ?? null;

        if(is_null($store_results)){
            $this->logger->debug("New Store Location: $store_id");

            $coordinates = $location_details->displayCoordinate ?? $location_details->geocodedCoordinate;
            $address = $location_details->address;

            $longitude = $coordinates->long;
            $latitude = $coordinates->lat;
            $city = $address->city;
            $postcode = $address->postalCode;

            $location->store_id = $store_id;

            $location->longitude = $longitude;
            $location->latitude = $latitude;

            $location->address_line1 = $address->line1;
            $location->address_line2 = $address->line2;
            $location->address_line3 = $address->line3;

            $location->city = $city;
            $location->postcode = $postcode;
            
            if(!$retrieve){
                $location->save();
            }
            
            return $location;

        } else {
            $this->logger->debug("Old Store Location: $store_id");
        }
        
    }

    public function opening_hours($store_id,$hours_details, $retrieve){
        $hours_details = $hours_details->hours;

        $normal_hours = $hours_details->normalHours;
        $holiday_hours =  $hours_details->holidayHours;

        $hours = [];

        foreach($normal_hours as $day_of_week => $hour_item){

            $opening_hours = new OpeningHoursModel($this->database);

            $hour_results = $opening_hours->where(['store_id' => $store_id,'day_of_week' => $day_of_week])->get()[0] ?? null;

            if(is_null($hour_results) || $retrieve){
                //New Hour Found
                $this->logger->debug("New Store Opening Hour Found. Store: $store_id. Day Of Week: $day_of_week");

                $opening_hours->closed_today = $hour_item->isClosed;

                $opening_hours->day_of_week = $day_of_week;
                $opening_hours->store_id = $store_id;

                if(!$opening_hours->closed_today){
                    // If Closed, no opens or closed
                    $time_details = $hour_item->intervals[0];
                    $opening_hours->opens_at = $this->format_time($time_details->start);
                    $opening_hours->closes_at = $this->format_time($time_details->end);
                } else {
                    $this->logger->debug("Store Closed Day: {$day_of_week}");
                }

                $opening_hours->closed_today = $opening_hours->closed_today ? 1 : 0;

                if(!$retrieve){
                    $opening_hours->save();
                }
                
                $hours[] = $opening_hours;

            } else {
                $this->logger->debug("Old Store Opening Hour Found. Store: $store_id. Day Of Week: $day_of_week");
            }
        }

        return $hours;

    }

    public function facilities($store_id,$facilities_details, $retrieve){
        
        $facilities_list = $facilities_details->c_facilitiesList;
        
        $facilities = [];

        if($facilities_details->c_petrolStation){
            $facilities_list[] = "Petrol Filling Station";
        }

        foreach($facilities_list as $facility_name){
            $facility = new FacilitiesModel($this->database);

            $facilities_results = $facility->where(['store_id' => $store_id, 'name' => $facility_name])->get()[0] ?? null;
            
            $facility->store_id = $store_id;
            $facility->name = $facility_name;

            if(is_null($facilities_results) || $retrieve){
                $this->logger->debug("New Store Facilities: $facility_name . $store_id ");

                if(!$retrieve){
                    $facility->save();
                }

                $facilities[] = $facility;
            } else {
                $this->logger->debug("Old Store Facilities: $store_id ");
            }

        }

        return $facilities;
    }

    public function format_time($time){
        // 830 -> 08:30:00
        // 2300 -> 23:00:00

        if($time == 0){
            $time = "0000";
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