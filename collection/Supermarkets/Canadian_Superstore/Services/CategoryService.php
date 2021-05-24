<?php

namespace Collection\Supermarkets\Canadian_Superstore\Services;

use Collection\Services\SharedRegionService;
use Exception;
use Collection\Supermarkets\Canadian_Superstore\CanadianSuperstore;
use Models\Category\CategoryModel;
use Models\Category\ChildCategoryModel;
use Models\Category\GrandParentCategoryModel;
use Models\Category\ParentCategoryModel;

class CategoryService extends CanadianSuperstore {

    private $region_service, $store_regions;

    private function setupServices(){
        if(is_null($this->region_service)){
            $this->region_service = new SharedRegionService($this->database_service);
            $this->store_regions = $this->region_service->get_regions($this->store_type_id);
        }
    }

    public function select_category($category, $type, $index){

        $category = (object)$category;

        $category_name = $category->name;
        $parent_category_id = $category->parent_category_id ?? null;
        $category_number = $category->number ?? null;

        $insert_fields = [
            'name' => $category_name,
            'site_category_id' => $category_number,
            'parent_category_id' => $parent_category_id,
            'store_type_id' => $this->store_type_id,
            'index' => $index
        ];

        if($type == 'grand_parent'){
            $category = new GrandParentCategoryModel($this->database_service);
            unset($insert_fields['parent_category_id']);
        } elseif($type == 'parent'){
            $category = new ParentCategoryModel($this->database_service);
        } elseif($type == 'child'){
            $category = new ChildCategoryModel($this->database_service);
        } else {
            throw new Exception("Unknown Category Type Found: $type");
        }

        $category_item = $category->where(['store_type_id' => $this->store_type_id, 'site_category_id' => $category_number])->get()[0] ?? null;

        if(!is_null($category_item)){
            // Update Index
            $category->where(['store_type_id' => $this->store_type_id, 'site_category_id' => $category_number])->update(['index' => $index]);
            $this->logger->debug($category_name . ' Category: Found In Database');
            return $category_item;
        } else {
            $this->logger->debug($category_name . ' Category: Not Found In Database');
            $category_insert_id = $category->create($insert_fields);

            $category_item = new CategoryModel();
            $category_item->id = $category_insert_id;
            $category_item->name = $category_name;
            $category_item->site_category_id = $category_number;
            $category_item->parent_category_id = $parent_category_id;

            return $category_item;

        }

    }

    public function category_products($category){
        $this->logger->debug("Fetch Products For Child Category: {$category->name}");

        $this->setupServices();
        
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

            foreach($category_results as $category_result){
                $pagination_data = $category_result->pagination;
                $total_results = $pagination_data->totalResults;
    
                $this->add_category_products($products, $category_result->results);
                
                if($total_results > $size){
                    $total_pages = ceil($total_results / $size);
                    $this->logger->debug("Total Pages: $total_pages");
        
                    for($page_number = 1; $page_number < $total_pages; $page_number++){
                        $paginated_category_results =  $this->request_category_data($category_number, $size, $page_number, $type);

                        foreach($paginated_category_results as $paginated_category_result){
                            $this->add_category_products($products, $paginated_category_result->results);
                        }
                    }
        
                } else {
                    $this->logger->debug('Total Pages: 1');
                }

            }

        }

        return array_unique($products);
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
            $response = $this->request_service->request($category_endpoint_v2, 'GET', [], ['Is-Pcs-Catalog' => 'true']);
            return [$this->request_service->parse_json($response)]; 
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
            $category_results = [];
            
            foreach($this->store_regions as $region){
                $region_name = $region->name;
                $store_id = $region->store_id;

                $this->logger->debug("Getting Catgory Products For {$region_name}[{$store_id}]");
                $category_results[] = $this->request_category_v3($category_endpoint_v3, $store_id, $page_number, $size, $category_number);
            }

            return $category_results;
        } catch(Exception $e){
            $this->logger->debug('V3 Category Request Error: ' . $e->getMessage());
        }

        return null;

    }

    private function request_category_v3($url, $store_id, $page_number, $size, $category_number){
        $response = $this->request_service->request($url, 'POST', [
            'pagination' => [
                'from' => $page_number,
                'size' => $size
            ],

            'banner' => 'superstore',
            'cartId' => '564d3383-738b-4407-b170-1064b504d991',
            'lang' => 'en',
            'storeId' => $store_id,
            'date' => '13032021',
            'pickupType' => 'STORE',
            'categoryId' => $category_number

        ], ['x-apikey' => '1im1hL52q9xvta16GlSdYDsTsG0dmyhF'], 300, 1);

        return $this->request_service->parse_json($response); 
    }

    private function add_category_products(&$products, $category_products){
        foreach($category_products as $product_data){
            $site_product_id =  $product_data->productId ?? $product_data->code;
            $products[] = $site_product_id;
        }
    }

}

?>