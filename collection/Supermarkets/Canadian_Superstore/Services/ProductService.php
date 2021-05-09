<?php

namespace Collection\Supermarkets\Canadian_Superstore\Services;

use Exception;
use Collection\Supermarkets\Canadian_Superstore\CanadianSuperstore;
use Interfaces\ProductRequestInterface;

class ProductService  extends CanadianSuperstore implements ProductRequestInterface {

    public function request_product($site_product_id, $request_type = null){

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

}

?>