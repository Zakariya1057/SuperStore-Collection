<?php

namespace Collection\Supermarkets\Canadian_Superstore\Services;

use Exception;
use Collection\Supermarkets\Canadian_Superstore\CanadianSuperstore;

class CategoryService extends CanadianSuperstore {

    public function category_products($category){
        $this->logger->debug("Fetch Products For Child Category: {$category->name}");

        if($this->env == 'dev'){
            $products = [];
        } else {
            $products = $this->request_category_products($category->number);
        }
        
        return $products;
    }

    private function request_category_products($category_number){
        $products = [];

        // Send Two Request. One for In-Store, One for Ship-To-Home
        // V2 - Ship To Home 
        // V3 - In Store

        $category_sources = ['v3', 'v2'];

        foreach($category_sources as $type){
            
            $size = 50;
            $page_number = 0;

            $this->logger->notice('--- Fetching Category Products: ' . $type);

            $category_results = $this->request_category_data($category_number, $size, $page_number, $type);

            $pagination_data = $category_results->pagination;
            $total_results = $pagination_data->totalResults;

            $this->add_category_products($products, $category_results->results);
    
            if($total_results > $size){
                $total_pages = ceil($total_results / $size);
                $this->logger->debug("Total Pages: $total_pages");
    
                for($page_number = 1; $page_number < $total_pages; $page_number++){
                    $category_data =  $this->request_category_data($category_number, $size, $page_number, $type);
                    $this->add_category_products($products, $category_data->results);
                }
    
            } else {
                $this->logger->debug('Total Pages: 1');
            }
        }

        return $products;
    }

    private function request_category_data($category_number, $size = 50, $page_number = 0, $type){
        return $type == 'v2' ? 
            $this->request_category_v2_data($category_number, $size, $page_number) : 
            $this->request_category_v3_data($category_number, $size, $page_number);
    }

    // V2 - Ship To Home
    private function request_category_v2_data($category_number, $size = 50, $page_number = 0){

        $categories_endpoints = $this->endpoints->categories;

        $category_endpoint_v2 = $categories_endpoints->v2 . $category_number . "&$page_number";

        $this->logger->debug('V2 Category Request Page Number: '. $page_number);

        try {
            $response = $this->request->request($category_endpoint_v2, 'GET', [], ['Is-Pcs-Catalog' => 'true']);
            return $this->request->parse_json($response); 
        } catch(Exception $e){
            $this->logger->debug('V2 Category Request Error: ' . $e->getMessage());
        }

        return null;

    }

    // V3 - In Store
    private function request_category_v3_data($category_number, $size = 50, $page_number = 0){

        $categories_endpoints = $this->endpoints->categories;
        $category_endpoint_v3 = $categories_endpoints->v3;

        $this->logger->debug('V3 Category Request Page Number: '. $page_number);

        try {
            $response = $this->request->request($category_endpoint_v3, 'POST', [
                'pagination' => [
                    'from' => $page_number,
                    'size' => $size
                ],

                'banner' => 'superstore',
                'cartId' => '564d3383-738b-4407-b170-1064b504d991',
                'lang' => 'en',
                'storeId' => '1077',
                'date' => '13032021',
                'pickupType' => 'STORE',
                'categoryId' => $category_number

            ], ['x-apikey' => '1im1hL52q9xvta16GlSdYDsTsG0dmyhF'], 300, 1);

            return $this->request->parse_json($response); 
        } catch(Exception $e){
            $this->logger->debug('V3 Category Request Error: ' . $e->getMessage());
        }

        return null;

    }

    private function add_category_products(&$products, $category_products){
        foreach($category_products as $product_data){
            $site_product_id =  $product_data->productId ?? $product_data->code;
            $products[] = $site_product_id;
        }
    }


}

?>