<?php

namespace Collection\Services;

use Models\Flyer\FlyerModel;
use Monolog\Logger;
use Services\ConfigService;
use Services\DatabaseService;
use Services\StorageService;

class SharedFlyerService {
    private $flyer_model;
    private $storage_service;

    public function __construct(ConfigService $config_service, Logger $logger, DatabaseService $database_service){
        $this->flyer_model = new FlyerModel($database_service);
        $this->storage_service = new StorageService($config_service, $logger);
    }

    private function get_flyer(int $store_id, int $store_type_id){
        return $this->store_model->where(['store_type_id' => $store_type_id, 'region' => $store_id])->first();
    }

    public function flyer_exists(int $store_id, int $store_type_id){
        $region = $this->get_flyer($store_id, $store_type_id);

        if(!is_null($region)){
            return $region->id;
        } else {
            return null;
        }
    }

    public function flyer_expired(int $store_id, int $store_type_id){
        $region = $this->get_flyer($store_id, $store_type_id);
    }
    
    public function delete_flyers($store_id){
        $this->flyer_model->where(['store_id' => $store_id])->delete();
    }

    public function create_flyers($flyers, $store_id){
        foreach($flyers as $flyer){
            $flyer->store_id = $store_id;
            $flyer_id = $flyer->save();

            $this->create_flyer_proudcts($flyer->products, $flyer_id);
        }
    }

    private function create_flyer_proudcts($products, $flyer_id){
        foreach($products as $product){
            $product->flyer_id = $flyer_id;
            $product->save();
        }
    }

    public function download_flyer($url, $name, $store_id){
        $data = file_get_contents($url);
        $path = str_replace(' ', '_', "flyers/{$name}_{$store_id}.pdf");
        return $this->storage_service->upload_s3($path, $data, 'pdf');
    }
}

?>