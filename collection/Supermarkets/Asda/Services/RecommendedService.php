<?php

namespace Collection\Supermarkets\Asda\Services;

use Collection\Supermarkets\Asda\Asda;
use Models\Product\ProductModel;
use Models\Product\RecommendedModel;

class RecommendedService extends Asda {

    private $recommended_model, $product_model;

    private function setupClasses(){
        if(is_null($this->recommended_model) || is_null($this->product_model)){
            $this->recommended_model = new RecommendedModel($this->database);
            $this->product_model = new ProductModel($this->database);
        }
    }

    public function request_recommended($site_product_id){
        $recommendation_endpoint = $this->endpoints->recommended . $site_product_id;

        if($this->env == 'dev'){
            $recommended_response = file_get_contents(__DIR__."/../../data/Asda/Recommendations.json");
        } else {
            $recommended_response = $this->request->request($recommendation_endpoint);
        }

        return $this->request->parse_json($recommended_response);
    }

    public function create($product_id, $recommended_product_id){
        $this->setupClasses();

        $recommended = clone $this->recommended_model;

        $recommended->product_id = $product_id;
        $recommended->recommended_product_id = $recommended_product_id;
        $recommended->insert_ignore = true;
        return $recommended->save();
    }

    public function delete_not_found($product_id, $product_ids){
        $this->setupClasses();

        if(count($product_ids) > 0){
            // Delete older recommended not found.
            $this->recommended_model->where(['product_id' => $product_id])->where_not_in('recommended_product_id',$product_ids)->delete();
        }
    }

    public function recommended_complete($product_id){
        $this->setupClasses();

        $this->product_model->where(['id' => $product_id])->update(['recommended_searched' => 1]);
    }
}

?>