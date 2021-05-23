<?php

namespace Collection\Supermarkets\Canadian_Superstore\Services;

use Collection\Supermarkets\Canadian_Superstore\CanadianSuperstore;
use Models\Store\FlyerModel;
use Services\StorageService;
use Symfony\Component\DomCrawler\Crawler;

class FlyerService extends CanadianSuperstore {
    private $flyer_model;
    private $storage_service;

    private function setupClasses(){
        if(is_null($this->flyer_model) || is_null($this->storage_service)){
            $this->flyer_model = new FlyerModel($this->database_service);
            $this->storage_service = new StorageService($this->config_service, $this->logger);
        }
    }

    public function store_exists(string $site_store_id, int $store_type_id){
        $store_results = $this->store_model->where(['store_type_id' => $store_type_id, 'site_store_id' => $site_store_id])->get()[0] ?? null;

        if(!is_null($store_results)){
            return $store_results->id;
        } else {
            return null;
        }
    }
    
    public function delete_flyers($store_id){
        $this->setupClasses();
        $this->flyer_model->where(['store_id' => $store_id])->delete();
    }

    public function get_flyers($site_store_id, $store_id): array {
        $this->setupClasses();

        $url = $this->endpoints->flyers->page . $site_store_id;
        $flyer_response = $this->request_service->request($url);

        $flyer_page = $this->request_service->parse_html($flyer_response);

        return $flyer_page->filter('li[data-flyer-id]')->each(function(Crawler $node) use($store_id){
            $flyer_id = $node->attr('data-flyer-id');
            
            $flyer = $this->request_flyer_data($flyer_id, $store_id);

            return $flyer;
        });
    }

    private function request_flyer_data($flyer_id, $store_id){
        $url = $this->endpoints->flyers->flyers . $flyer_id;
        $flyer_response = $this->request_service->request($url);

        $flyer_data = $this->request_service->parse_json($flyer_response);
        return $this->parse_flyer($flyer_data, $store_id);
    }

    private function parse_flyer($flyer_data, $store_id){
        $flyer = clone $this->flyer_model;

        $flyer->name = $flyer_data->flyer_run_external_name;

        $flyer->site_flyer_id = $flyer_data->id;
        $flyer->store_type_id = $this->store_type_id;

        $flyer->store_id = $store_id;

        $flyer->url = $this->download_flyer($flyer_data->pdf_url, $flyer->name, $store_id);

        $flyer->valid_from = date('Y-m-d H:i:s', strtotime( $this->clean_valid_date($flyer_data->valid_from) ));
        $flyer->valid_to = date('Y-m-d H:i:s', strtotime( $this->clean_valid_date($flyer_data->valid_to) ));
        
        $this->logger->debug($flyer_data->valid_from . ' -> ' . $flyer->valid_from);
        $this->logger->debug($flyer_data->valid_to . ' -> ' . $flyer->valid_to);

        return $flyer;
    }

    private function clean_valid_date($date){
        return preg_replace('/-\d{2}:\d{2}$/', '', $date);
    }
    
    private function download_flyer($url, $name, $store_id){
        $data = file_get_contents($url);
        $path = str_replace(' ', '_', "flyers/{$name}_{$store_id}.pdf");
        return $this->storage_service->upload_s3($path, $data, 'pdf');
    }
}

?>