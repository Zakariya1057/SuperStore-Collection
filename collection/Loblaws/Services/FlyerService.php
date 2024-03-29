<?php

namespace Collection\Loblaws\Services;

use Collection\Services\SharedFlyerService;
use Collection\Loblaws\Loblaws;
use Models\Flyer\FlyerModel;
use Services\StorageService;
use Symfony\Component\DomCrawler\Crawler;

class FlyerService extends Loblaws {
    private $storage_service;
    private $shared_flyer_service;
    private $flyer_product_service;

    private function setupClasses(){
        if(is_null($this->storage_service)){
            $this->storage_service = new StorageService($this->config_service, $this->logger);
            $this->shared_flyer_service = new SharedFlyerService($this->config_service, $this->logger, $this->database_service);
            $this->flyer_product_service = new FlyerProductService($this->config_service, $this->logger, $this->database_service);
        }
    }

    public function get_flyers($site_store_id, $store_id, string $banner): array {
        $this->setupClasses();

        $url = $this->endpoints->flyers->page . "$banner?type=1&store_code=" . preg_replace('/^0/', '', $site_store_id);
        $flyer_response = $this->request_service->request($url);

        $flyers = [];

        preg_match("/window\['hostedStack'\] = (.+);/", $flyer_response, $matches);

        if(count($matches) > 1){
            $flyers_data = $this->request_service->parse_json( $matches[1] );

            foreach($flyers_data as $flyer_data){
                $flyer_id = $flyer_data->current_flyer_id;
                $flyers[] = $this->request_flyer_data($flyer_id, $store_id);
            }
        }

        return $flyers;
    }

    private function request_flyer_data($flyer_id, $store_id){
        $url = $this->endpoints->flyers->flyers . $flyer_id;
        $flyer_response = $this->request_service->request($url);

        $flyer_data = $this->request_service->parse_json($flyer_response);
        return $this->parse_flyer($flyer_data, $store_id);
    }

    private function parse_flyer($flyer_data, $store_id){
        $flyer = new FlyerModel($this->database_service);

        $flyer->name = $flyer_data->flyer_run_external_name;

        $flyer->site_flyer_id = $flyer_data->id;

        $flyer->store_id = $store_id;

        $flyer->url = $this->shared_flyer_service->download_flyer($flyer_data->pdf_url, $flyer->name, $store_id);

        $flyer->valid_from = date('Y-m-d H:i:s', strtotime( $this->clean_valid_date($flyer_data->valid_from) ));
        $flyer->valid_to = date('Y-m-d H:i:s', strtotime( $this->clean_valid_date($flyer_data->valid_to) ));
        
        $this->logger->debug($flyer_data->valid_from . ' -> ' . $flyer->valid_from);
        $this->logger->debug($flyer_data->valid_to . ' -> ' . $flyer->valid_to);

        $this->flyer_product_service->set_flyer_products($flyer, $flyer_data);

        return $flyer;
    }

    private function clean_valid_date($date){
        return preg_replace('/-\d{2}:\d{2}$/', '', $date);
    }
}

?>