<?php

namespace Supermarkets\Canadian_Superstore\Services;

use Exception;
use Models\Product\ProductModel;
use Monolog\Logger;
use Services\Database;
use Supermarkets\Canadian_Superstore\CanadianSuperstore;

class ProductService extends CanadianSuperstore {
    
    private $product_model;

    private function setupProductModel(){
        if(is_null($this->product_model)){
            $this->product_model = new ProductModel($this->database);
        }
    }

    public function request_product($site_product_id, $request_type = null): ?array {

        $product_response = null;
        $product_details = null;

        $this->logger->debug('Request Type: ' . $request_type);

        $product_endpoints = $this->endpoints->products;

        $retry_times = !is_null($request_type) ? 3 : 1;

        if(is_null($request_type) || $request_type == 'v3'){
            $endpoint_v3 = $product_endpoints->v3 . "$site_product_id?lang=en&storeId=1077&banner=superstore";
            
            try {
                $product_response = $this->request->request($endpoint_v3, 'GET', [], ['x-apikey' => '1im1hL52q9xvta16GlSdYDsTsG0dmyhF'], 300, $retry_times);
                $product_details = $this->request->parse_json($product_response);

                $request_type = 'v3';
    
            } catch (Exception $e){
                $this->logger->debug('Product V3 Endpoint Error: ' . $site_product_id . ' -> ' . $e->getMessage());
            }
        }
        
        if(is_null($product_response)){
            
            $endpoint_v2 = $product_endpoints->v2 . $site_product_id;

            try {
                $product_response = $this->request->request($endpoint_v2, 'GET', [], [], 300, $retry_times);
                $product_details = $this->request->parse_json($product_response);

                $request_type = 'v2';
            } catch(Exception $e){
                $this->logger->error('Product Not Found On Either Endpoints: ' . $e->getMessage());
                // throw new Exception('Product Not Found On Either Endpoints');
                return null;
            }

        }
        
        // dd($request_type);

        return ['response' => $product_details, 'type' => $request_type];
    }

    public function product_exists($site_product_id, $store_type_id){
        // $product_model = new ProductModel($this->database);
        $this->setupProductModel();

        $product_results = $this->product_model->where(['store_type_id' => $store_type_id, 'site_product_id' => $site_product_id])->get()[0] ?? null;

        if(!is_null($product_results)){
            return $product_results->id;
        } else {
            return null;
        }
    }

    public function create_product($product){
        return $product->save();
    }

    public function create_barcodes($product_id, $product){
        if(key_exists('barcodes', $product)){
            foreach($product->barcodes as $barcode){
                $barcode->product_id = $product_id;
                $barcode->save();
            }
        }
    }

    public function create_ingredients($product_id, $product){
        if(count($product->ingredients) > 0){
            foreach($product->ingredients as $ingredient){
                $ingredient->product_id = $product_id;
                $ingredient->insert_ignore = true;
                $ingredient->save();
            }
        }
    }

    public function create_promotion(ProductModel &$product){
        if(property_exists($product, 'promotion') && !is_null($product->promotion)){
            if(property_exists($product->promotion, 'id')){
                $product->promotion_id = $product->promotion->id;
            } else {
                $product->promotion_id = $product->promotion->save();
            }
        }
    }

    public function create_images($product_id, $product){
        foreach($product->images as $image){
            $image->product_id = $product_id;
            $image->insert_ignore = true;
            $image->save();
        }
    }
}

?>