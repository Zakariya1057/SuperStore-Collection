<?php

namespace Stores\Asda;

use Exception;
use Models\Store\FacilitiesModel;
use Models\Store\LocationModel;
use Models\Store\OpeningHoursModel;
use Models\Store\StoreModel;

class AsdaStores extends Asda {

    function __construct($config,$logger,$database)
    {
        parent::__construct($config,$logger,$database);
    }

    public function stores(){
        $stores_endpoint =  $this->endpoints->stores . $this->city;

        $this->logger->notice("Finding All Stores In: $this->city");

        if($this->env == "dev"){
            $stores_response = file_get_contents(__DIR__."/../../Data/Asda/Stores.json");
        } else {
            $stores_response = $this->request->request($stores_endpoint);
        }

        $stores_results = $this->request->parse_json($stores_response);

        foreach($stores_results->response->entities as $item){
            $this->store($item);
        }

    }

    public function store($store_item){

        $item_details = $store_item->profile;

        $id = $item_details->meta->id;
        $name = $item_details->name;
        $description = $item_details->c_aboutSectionDescription;
        $site_image = $item_details->googleCoverPhoto->image->sourceUrl ?? NULL;
        $site_url = $item_details->c_pagesURL;
        $uber_url = $store_item->derivedData->uber->url;
        $google_url = $store_item->listings->googleMyBusiness->url;

        $store = new StoreModel($this->database);

        $store_results = $store->where(['store_site_id' => $id])->get();

        if(!$store_results){
            $this->logger->debug("New Store: $name ($id)");

            $new_store = $store;
            $new_store->name = $name;
            $new_store->store_site_id = $id;
            $new_store->store_type_id = $this->site_type_id;
            $new_store->description = $description;
            $new_store->store_image = 
            $new_store->site_url = $site_url;
            $new_store->uber_url = $uber_url;
            $new_store->google_url = $google_url;
            $new_store->store_image = $site_image;

            $new_store_id = $new_store->save();

        } else {
            $this->logger->debug("Old Store: $name ($id)");
            $new_store_id = $store_results->id;
        }

        //New Location will be inserted if location not found in database
        $this->location($new_store_id,$item_details);
        $this->opening_hours($new_store_id,$item_details);
        $this->facilities($new_store_id,$item_details);
    }

    public function location($store_id,$location_details){
        //Insert Store Locations
        $location = new LocationModel($this->database);

        $store_results = $location->where(['store_id' => $store_id])->get();

        if(!$store_results){
            $this->logger->debug("New Store Location: $store_id");

            $coordinates = $location_details->cityCoordinate;
            $address = $location_details->address;

            $longitude = $coordinates->long;
            $latitude = $coordinates->lat;

            $location->store_id = $store_id;

            $location->longitude = $longitude;
            $location->latitude = $latitude;

            $location->address_line1 = $address->line1;
            $location->address_line2 = $address->line2;
            $location->address_line3 = $address->line3;
            
            $location->save();
        } else {
            $this->logger->debug("Old Store Location: $store_id");
        }
        
    }

    public function opening_hours($store_id,$hours_details){
        //Insert new opening hours
       
        $hours_details = $hours_details->hours;

        $normal_hours = $hours_details->normalHours;
        $holiday_hours =  $hours_details->holidayHours;

        foreach($normal_hours as $day_of_week => $hour_item){

            $opening_hours = new OpeningHoursModel($this->database);

            $hour_results = $opening_hours->where(['store_id' => $store_id,'day_of_week' => $day_of_week])->get();

            if(!$hour_results){
                //New Hour Found
                $this->logger->debug("New Store Opening Hour Found. Store: $store_id. Day Of Week: $day_of_week");

                $time_details = $hour_item->intervals[0];

                $opening_hours->closed_today = $hour_item->isClosed == false ? NULL : true;
                $opening_hours->day_of_week = $day_of_week;
                $opening_hours->store_id = $store_id;
                $opening_hours->open = $this->format_time($time_details->start);
                $opening_hours->close = $this->format_time($time_details->end);

                $opening_hours->save();

            } else {
                $this->logger->debug("Old Store Opening Hour Found. Store: $store_id. Day Of Week: $day_of_week");
            }
        }

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

    public function facilities($store_id,$facilities_details){
        
        $facilities_list = $facilities_details->c_facilitiesList;
        
        foreach($facilities_list as $facility_name){
            $facility = new FacilitiesModel($this->database);

            $facilities_results = $facility->where(['store_id' => $store_id, 'name' => $facility_name])->get();
            
            if(!$facilities_results){
                $this->logger->debug("New Store Facilities: $facility_name . $store_id ");
                $facility->name = $facility_name;
                $facility->store_id = $store_id;

                $facility->save();

            } else {
                $this->logger->debug("Old Store Facilities: $store_id ");
            }

        }

    }



}

?>