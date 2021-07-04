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

    private $region_stores = [
        8 => 1077,
        9 => 1502,
        10 => 1511,
        11 => 1556,
        12 => 1533,
        13 => 1530
    ];

    public function region_exists(string $name, int $company_id){
        $region_results = $this->region_model->where(['name' => $name, 'company_id' => $company_id])->first();

        if(!is_null($region_results)){
            return $region_results->id;
        } else {
            return null;
        }
    }

    public function get_regions(int $company_id){
        
        if(is_null($this->regions)){
            $regions_results = $this->region_model->select(['id', 'name'])->where(['company_id' => $company_id])->get();

            foreach($regions_results as $region){
                
                // $store = $this->store_location_model
                // ->select(['site_store_id'])
                // ->join('stores', 'stores.id', 'store_locations.store_id')
                // ->where(['region_id' => $region->id])->order_by('site_store_id', 'DESC')->first();
    
                // if(!is_null($store)){
                //     $region->store_id = $store->site_store_id;
                // } else {
                //     throw new Exception('Region Without Store ID Found: '. $region->id);
                // }

                $region->store_id = $this->region_stores[$region->id];
            }

            $this->regions = $regions_results;
        } else {
            return $this->regions;
        }

        return $regions_results;
    }

    public function create_region(RegionModel $region){
        if($region_id = $this->region_exists($region->name, $region->company_id)){
            return $region_id;
        } else {
            return $region->save();
        }
    }

}

?>