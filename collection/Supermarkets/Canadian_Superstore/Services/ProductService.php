<?php

namespace Collection\Supermarkets\Canadian_Superstore\Services;

use Exception;
use Collection\Supermarkets\Canadian_Superstore\CanadianSuperstore;
use Interfaces\ProductRequestInterface;
use Models\Product\ProductModel;

class ProductService extends CanadianSuperstore implements ProductRequestInterface {

    public function request_product($site_product_id, $site_store_id){

        $product_response = null;
        $product_details = null;

        $product_endpoints = $this->endpoints->products;

        $retry_times = 1;

        $request_type = null;

        if(str_contains(strtoupper($site_product_id), '_')){ 
            $endpoint_v3 = $product_endpoints->v3 . "$site_product_id?lang=en&pickupType=STORE&banner=superstore&storeId=$site_store_id";
            
            try {
                $product_response = $this->request_service->request($endpoint_v3, 'GET', [], ['x-apikey' => '1im1hL52q9xvta16GlSdYDsTsG0dmyhF'], 300, $retry_times);
                $product_details = $this->request_service->parse_json($product_response);

                $request_type = 'v3';
    
            } catch (Exception $e){
                $this->logger->debug('Product V3 Endpoint Error: ' . $site_product_id . ' -> ' . $e->getMessage());
                return null;
            }
        } else {
            $endpoint_v2 = $product_endpoints->v2 . $site_product_id;

            try {
                $product_response = $this->request_service->request($endpoint_v2, 'GET', [], [], 300, $retry_times);
                $product_details = $this->request_service->parse_json($product_response);

                $request_type = 'v2';
            } catch(Exception $e){
                $this->logger->error('Product Not Found On Either Endpoints: ' . $e->getMessage());
                // throw new Exception('Product Not Found On Either Endpoints');
                return null;
            }
        }

        return ['response' => $product_details, 'type' => $request_type];
    }


    public function create_name($name, $brand = null){

        $new_name = trim($name);

        // Add Brand to name if brand not null
        if(!is_null($brand) && strtolower($brand) != 'no name'){

            // If any part of brand found in name, then exclde
            $brand_regex = str_replace(' ', '|', $brand);

            preg_match('/' . $brand_regex . '/i', $name, $brand_matches);

            if($brand_matches){
                $this->logger->debug("Brand($brand) Found In Product Name: $name");
            } else {
                $this->logger->debug("Brand($brand) Not Found In Product Name: $name");
                $new_name = trim(trim($brand) .' '. trim($name));
            }

            if(strlen($new_name) > 255){
                $new_name = substr($new_name, 0, 248) . '...';
            }

        }

        return $new_name;
    }
    
    public function set_description(ProductModel $product, $description) {
        preg_match('/(.+)Features(.+)Dimensions(.+)/i', $description, $matches);

        if($matches){
            $start_description = $matches[1];
            $features = $matches[2];
            $dimensions = $matches[3];
            
            $product->features = $this->create_description($features);
            $product->dimensions = $this->create_description($dimensions);
            $product->description = str_replace('..','.', preg_replace('/<\/*\w+>\s*<\/*\w+>/',".\n\n", $start_description));
        } else {
            $product->description = str_replace('..','.', preg_replace('/<\/*\w+>\s*<\/*\w+>/',".\n\n", $description));
        }

        $product->description = trim($product->description) == '' ? NULL : $product->description;
    }

    private function create_description($description){
        $description_list = $this->seperate_description($description) ?? [$description];

        $description_output = [];

        foreach($description_list as $description_item){
            $item = $this->clean_description_name($description_item);

            if($item != ''){
                $description_output[] = $item;
            }
        }
        
        return json_encode($description_output);
    }

    public function seperate_description($description){
        $description = preg_replace('/<\/*\S+>/',"\n", $description);
        return explode("\n", $description);
    }

    public function clean_description_name($description){
        $description = preg_replace('/^-\s+|^:|Specifications\//','', $description);
        $description = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "", $description);
        $description = $this->sanitize_service->sanitize_field($description);
        return strip_tags($description);
    }

    public function create_image($name, $url, $size): ?string {
        return $this->image_service->save($name, $url, $size, "products", $this->store_name);
    }

}

?>