<?php

namespace Stores\Asda;

use Exception;
use Models\Product\ProductModel;
use Models\Product\RecommendedModel;
use Monolog\Logger;
use Shared\Config;
use Shared\Database;
use Shared\Remember;
class AsdaRecommended extends Asda {

    public $productModel;

    function __construct(Config $config, Logger $logger, Database $database, Remember $remember=null)
    {
        parent::__construct($config,$logger,$database,$remember);
        $this->productModel = new ProductModel($this->database);
    }

    public function all_recommended_products(){
        //Loop through all product in database without related products and set their related products.
        $this->logger->notice('------ Product Recommended Start ---------');

        $products_without_recommended = $this->productModel->select(['id','site_product_id','name'])->where(['recommended_searched' => null])->order_by('id','ASC')->get();
        
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
                $this->database->commit_transaction();
            }

        } else {
            $this->logger->notice('No Product Without Recommended Found');
        }

        $this->logger->notice('------ Product Recommended Complete ---------');

    }

    public function product_recommended($product_id, $site_product_id){

        $recommendation_endpoint = $this->endpoints->recommended . $site_product_id;

        if($this->env == 'dev'){
            $recommended_response = file_get_contents(__DIR__."/../../Data/Asda/Recommendations.json");
        } else {
            $recommended_response = $this->request->request($recommendation_endpoint);
        }

        $recommended_data = $this->request->parse_json($recommended_response);

        $product = new ProductModel($this->database);

        if(property_exists($recommended_data, 'results') && count($recommended_data->results) > 0){
        
            $results = $recommended_data->results[0]->items;

            $product_ids = [];

            $recommended = new RecommendedModel($this->database);

            foreach($results as $item){

                if($item->id == 1000197472184){
                    // ASDA Grower's Selection 7 Bananas
                    $this->logger->debug('Ignore banana recommened item. Return on unrelated random items so ignore from here on out');
                    continue;
                }

                $new_prduct_details = $product->where(['site_product_id' => $item->id])->get()[0] ?? null;

                if(!is_null($new_prduct_details)){
                    $recommended->product_id = $product_id;
                    $recommended->recommended_product_id = $new_prduct_details->id;
                    $recommended->insert_ignore = true;
                    $recommended->save();
                    $product_ids[] = $new_prduct_details->id;
                } else {
                    $this->logger->warning('Similar Product Not Found In Database. Creating The Product, Then Setting As Recommened');

                    $new_product = new AsdaProducts($this->config,$this->logger,$this->database,$this->remember);
                    $new_product_id = $new_product->product($item->id,null,null,null,$this->sanitize->sanitize_field($item->aisleName));

                    if($new_product_id){
                        $this->logger->debug('Setting New Product As Recommened');

                        $recommended->product_id = $product_id;
                        $recommended->recommended_product_id = $new_product_id;
                        $recommended->insert_ignore = true;
                        $recommended->save();
                        $product_ids[] = $new_product_id;
                    } else {
                        $this->logger->debug('New Product Not Added. Ignoring');
                    }

                }

            }

            if(count($product_ids) > 0){
                // Delete older recommended not found.
                $recommended->where(['product_id' => $product_id])->where_not_in('recommended_product_id',$product_ids)->delete();
            }

        }

        $this->productModel->where(['id' => $product_id])->update(['recommended_searched' => 1]);

    }

}

?>