<?php

namespace Collection\Supermarkets\Canadian_Superstore\Services;

use Collection\Supermarkets\Canadian_Superstore\CanadianSuperstore;
use Models\Store\FlyerModel;
use Symfony\Component\DomCrawler\Crawler;

class FlyerService extends CanadianSuperstore {
    private $flyer_model;

    private function setupModel(){
        if(is_null($this->flyer_model)){
            $this->flyer_model = new FlyerModel($this->database_service);
        }
    }

    public function get_flyers($site_store_id, $store_id){

        $this->setupModel();

        $url = $this->endpoints->flyers->page . $site_store_id;
        $flyer_response = $this->request_service->request($url);

        $flyer_page = $this->request_service->parse_html($flyer_response);

        $flyers_list = $flyer_page->filter('li[data-flyer-id]')->each(function(Crawler $node) use($store_id){
            $flyer_id = $node->attr('data-flyer-id');
            
            $flyer = $this->request_flyer_data($flyer_id);
            $flyer->store_id = $store_id;
            
            return $flyer;
        });

        dd($flyers_list);
    }

    private function request_flyer_data($flyer_id){
        $url = $this->endpoints->flyers->flyers . $flyer_id;
        $flyer_response = $this->request_service->request($url);

        $flyer_data = $this->request_service->parse_json($flyer_response);
        return $this->parse_flyer($flyer_data);
    }

    private function parse_flyer($flyer_data){
        $flyer = clone $this->flyer_model;

        $flyer->name = $flyer_data->flyer_run_external_name;

        $flyer->site_flyer_id = $flyer_data->id;
        $flyer->store_type_id = $this->store_type_id;

        $flyer->url = $flyer_data->pdf_url;

        $flyer->valid_from = date('Y-m-d H:i:s', strtotime( $flyer_data->valid_from ));
        $flyer->valid_to = date('Y-m-d H:i:s', strtotime( $flyer_data->valid_to));

        return $flyer;
    }

    private function download_flyer($url){

    }
}

?>