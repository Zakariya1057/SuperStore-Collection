<?php

namespace Services;

use Exception;
use Monolog\Logger;

class ImageService {

    private $logger, $img_config, $request_service;
    private $storage_service;

    function __construct(ConfigService $config_service, Logger $logger, RequestService $request_service){
        $this->logger = $logger;
        $this->request_service = $request_service;
        
        $this->img_config = $config_service->get('images');

        $this->storage_service = new StorageService($config_service, $logger);
    }

    public function save($name, $image_url,$size='',$type='products', $store='Asda'){

        $store = str_replace(' ', '_', $store);

        $name = "{$name}_{$size}.jpg";
        $type = $this->type_directories($type);
        $image = $this->get_image($image_url);

        $this->logger->debug("Image URL: $image_url");

        if(is_null($image)){
            return;
        }

        $file_path = "$type/{$store}_{$name}";

        if($this->img_config->saving_location == 'local'){
            $this->logger->debug('Saving Images On Local Machine');
            $file_location = $this->storage_service->store_local($file_path, $image);
        } else {
            $this->logger->debug('Saving Images On AWS S3 Bucket');
            $file_location = $this->storage_service->upload_s3($file_path, $image);
        }
        
        return $file_location;
    }

    private function type_directories($type){
        $locations = $this->img_config->type_locations;
        
        strtolower($type);

        if(property_exists($locations, $type)){
            return $locations->{$type};
        } else {
            throw new Exception('Unknown Data Saving Option: '.$type);
        }
    }

    private function get_image($url){
        try {
            return $this->request_service->request($url, 'GET', [], [], 300, 1);
        } catch (Exception $e){
            $this->logger->error('Failed To Get Image: '. $e->getMessage());
            return;
        }
    }


}

?>