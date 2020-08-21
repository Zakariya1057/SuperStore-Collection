<?php

namespace Stores\Asda;

use Exception;
use Models\Product\ProductModel;
use Models\Product\RecommendedModel;

class AsdaRecommended extends Asda {

    public $productModel;

    function __construct($config,$logger,$database,$remember)
    {
        parent::__construct($config,$logger,$database,$remember);
        $this->productModel = new ProductModel($this->database);
    }

    public function all_recommended_products(){
        //Loop through all product in database without related products and set their related products.
        // $this->logger->notice('Getting All Recommended Products');

        // //Get all products from database, get recommended

        // $this->product_recommended(13);

        $this->logger->notice('------ Product Recommended Start ---------');

        $products_without_recommended = $this->productModel->select(['id','site_product_id','name'])->where(['recommended_searched' => null])->get();

        if(is_object($products_without_recommended) ){
            $products_without_recommended = [$products_without_recommended];
        }
        
        if($products_without_recommended){

            $product_count = count($products_without_recommended);

            $this->logger->debug("Found $product_count Products Without Recommended");
    
            foreach($products_without_recommended as $product){
                $name = $product->name;
                $product_id = $product->id;
                $site_product_id = $product->site_product_id;
    
                $this->logger->debug("New Product To Find Recommended Item: [$product_id]$name");
    
                $this->database->start_transaction();
                $this->product_recommended($product_id, $site_product_id);
                $this->database->end_transaction();
            }

        } else {
            $this->logger->notice('No Product Without Recommended Found');
        }

        $this->logger->notice('------ Product Recommended Complete ---------');

    }

    // Id,parent_product_id, product_id, site_product_id, created_at,
    public function product_recommended($product_id, $site_product_id){

        $recommendation_endpoint = $this->endpoints->recommended . $site_product_id;

        if($this->env == "dev"){
            $recommended_response = file_get_contents(__DIR__."/../../Data/Asda/Recommendations.json");
        } else {
            $recommended_response = $this->request->request($recommendation_endpoint);
        }

        $recommended_data = $this->request->parse_json($recommended_response);

        $product = new ProductModel($this->database);

        $results = $recommended_data->results[0]->items;

        foreach($results as $item){
            $recommended = new RecommendedModel($this->database);

            $new_prduct_details = $product->where(['site_product_id' => $item->id])->get();

            if($new_prduct_details){
                $recommended->product_id = $product_id;
                $recommended->recommended_product_id = $new_prduct_details->id;
                $recommended->insert_ignore = true;
                $recommended->save();
            } else {
                $this->logger->warning('Similar Product Not Found In Database. Creating The Product, Then Setting As Recommened');

                $new_product = new AsdaProducts($this->config,$this->logger,$this->database,$this->remember);
                
                $new_product_id = $new_product->product($item->id,null,$this->sanitize->sanitizeField($item->aisleName));

                if($new_product_id){
                    $recommended->product_id = $product_id;
                    $recommended->recommended_product_id = $new_product_id;
                    $recommended->insert_ignore = true;
                    $recommended->save();
                }

            }

        }

        $this->productModel->where(['id' => $product_id])->update(['recommended_searched' => 1]);

    }

}

?>