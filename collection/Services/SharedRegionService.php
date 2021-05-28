<?php

namespace Collection\Services;

use Exception;
use Models\Store\LocationModel;
use Models\Store\RegionModel;
use Services\DatabaseService;

class SharedRegionService {

    private $region_model, $store_location_model;
    private $regions;

    public function __construct(DatabaseService $database_service){
        $this->region_model = new RegionModel($database_service);
        $this->store_location_model = new LocationModel($database_service);
    }

    public function region_exists(string $name, int $store_type_id){
        $region_results = $this->region_model->where(['name' => $name, 'store_type_id' => $store_type_id])->first();

        if(!is_null($region_results)){
            return $region_results->id;
        } else {
            return null;
        }
    }

    public function get_regions($store_type_id){
        if(is_null($this->regions)){
            $regions_results = $this->region_model->select(['id', 'name'])->where(['store_type_id' => $store_type_id])->get();

            foreach($regions_results as $region){
                $store = $this->store_location_model
                ->select(['site_store_id'])
                ->join('stores', 'stores.id', 'store_locations.store_id')
                ->where(['region_id' => $region->id])->first();
    
                if(!is_null($store)){
                    $region->store_id = $store->site_store_id;
                } else {
                    throw new Exception('Region Without Store ID Found: '. $region->id);
                }
            }

            $this->regions = $regions_results;
        } else {
            return $this->regions;
        }

        return $regions_results;
    }

    public function create_region(RegionModel $region){
        if($region_id = $this->region_exists($region->name, $region->store_type_id)){
            return $region_id;
        } else {
            return $region->save();
        }
    }

}

?>