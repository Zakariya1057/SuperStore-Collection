<?php

namespace Stores\Asda;

use Exception;
use Models\Product\ProductModel;
use Models\Product\RecommendedModel;

class AsdaRecommended extends Asda {

    function __construct($config,$logger,$database)
    {
        parent::__construct($config,$logger,$database);
    }

    public function all_recommended_products(){
        //Loop through all product in database without related products and set their related products.
        $this->logger->notice('Getting All Recommended Products');
        $this->product_recommended(13);
    }

    // Id,parent_product_id, product_id, site_product_id, created_at,
    public function product_recommended($product_id,$product_details=null){

        $recommendation_endpoint = $this->endpoints->recommended . $product_id;

        if($this->env == "dev"){
            $recommended_response = file_get_contents(__DIR__."/../../Data/Asda/Recommendations.json");
        } else {
            $recommended_response = $this->request->request($recommendation_endpoint);
        }

        $recommended_data = $this->request->parse_json($recommended_response);

        if(!$product_details){
            $product = new ProductModel($this->database);
            $product_details = $product->where(['id' => $product_id])->get();

            if(!$product_details){
                throw new Exception('No Product Details Found');
            }
        }

        $results = $recommended_data->results[0]->items;

        foreach($results as $item){
            $recommended = new RecommendedModel($this->database);

            $new_prduct_details = $product->where(['site_product_id' => $item->id])->get();

            if($new_prduct_details){
                $recommended->product_id = $product_details->id;
                $recommended->recommended_product_id = $new_prduct_details->id;
                $recommended->save();
            } else {
                $this->logger->warning('Similar Product Not Found In Database');

                $new_product = new AsdaProducts($this->config,$this->logger,$this->database);
                
                $new_product_id = $new_product->product($item->id,null,$this->sanitize->sanitizeField($item->aisleName));

                if($new_product_id){
                    $recommended->product_id = $product_details->id;
                    $recommended->recommended_product_id = $new_product_id;
                    $recommended->save();
                }

            }

        }

    }

}

?>