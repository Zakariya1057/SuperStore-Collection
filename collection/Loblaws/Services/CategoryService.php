<?php

namespace Collection\Loblaws\Services;

use Exception;
use Collection\Loblaws\Loblaws;
use Models\Category\CategoryModel;
use Models\Category\ChildCategoryModel;
use Models\Category\GrandParentCategoryModel;
use Models\Category\ParentCategoryModel;
use Models\Product\ProductModel;

class CategoryService extends Loblaws {

    private $product_model;

    private function setupServices(){
        if(is_null($this->product_model)){
            $this->product_model = new ProductModel($this->database_service);
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
            'company_id' => $this->company_id,
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

        $category_item = $category->where(['company_id' => $this->company_id, 'site_category_id' => $category_number])->first();

        if(!is_null($category_item)){
            // Update Index
            $category->where(['company_id' => $this->company_id, 'site_category_id' => $category_number])->update(['index' => $index]);
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
            $products = $this->request_category_products($category->number, $category->id);
        }
        
        return $products;
    }

    private function request_category_products($category_number, $category_id){
        $products = [
            // site_product_id => site_product_id
        ];

        // Send Two Request. One for In-Store, One for Ship-To-Home
        // V2 - Ship To Home 
        // V3 - In Store

        $category_sources = [
            'v3', 
            'v2'
        ];

        foreach($category_sources as $type){
            
            $size = 50;
            $page_number = 0;

            $this->logger->notice('------- Starting Fetching Category Products: ' . $type);

            $category_results = $this->request_category_data($category_number, $size, $page_number, $type);

            $category_result = $category_results[0];
            
            $pagination_data = $category_result->pagination;
            $total_results = $pagination_data->totalResults;

            $this->add_category_products($products, $category_result->results);
            
            if($total_results > $size){
                $total_pages = ceil($total_results / $size);
                $this->logger->notice("Total Pages: $total_pages");
    
                for($page_number = 1; $page_number < $total_pages; $page_number++){
                    $paginated_category_results = $this->request_category_data($category_number, $size, $page_number, $type);

                    foreach($paginated_category_results as $paginated_category_result){
                        $this->add_category_products($products, $paginated_category_result->results);
                    }
                }
    
            } else {
                $this->logger->debug('Total Pages: 1');
            }

            $this->logger->notice('------- Complete Fetching Category Products: ' . $type);

        }

        return $this->unique_new_products($products, $category_id);

    }

    private function request_category_data($category_number, int $size = 50, int $page_number = 0, string $type){
        return $type == 'v2' ? 
            $this->request_category_v2_data($category_number, $size, $page_number) : 
            $this->request_category_v3_data($category_number, $size, $page_number);
    }

    // V2 - Ship To Home
    private function request_category_v2_data($category_number, int $page_number = 0){

        foreach($this->supermarket_chains as $supermarket_chain){

            $supermarket_name = $supermarket_chain->name;
            $supermarket_banner = $supermarket_chain->banner;

            $this->logger->notice("----- V2 $supermarket_name Category Products");

            $categories_endpoints = $this->endpoints->categories->v2;

            $url = $categories_endpoints->first_part . $supermarket_banner .  $categories_endpoints->last_part . $category_number . "&$page_number";
    
            $this->logger->notice('V2 Category Request Page Number: '. $page_number);
    
            try {
                $response = $this->request_service->request($url, 'GET', [], ['Is-Pcs-Catalog' => 'true']);
                return [$this->request_service->parse_json($response)]; 
            } catch(Exception $e){
                $this->logger->error('V2 Category Request Error: ' . $e->getMessage());
            }

        }

        return null;

    }

    // V3 - In Store
    private function request_category_v3_data($category_number, int $size = 50, int $page_number = 0){

        $categories_endpoints = $this->endpoints->categories;
        $category_endpoint_v3 = $categories_endpoints->v3;

        $this->logger->debug('V3 Category Request Page Number: '. $page_number);

        try {
            $category_results = [];
            
            foreach($this->supermarket_chains as $supermarket_chain){
                $regions = $supermarket_chain->regions;

                $supermarket_name = $supermarket_chain->name;
                $supermarket_banner = $supermarket_chain->banner;

                $this->logger->debug("----- V3 $supermarket_name Category Products");

                foreach($regions as $region_name => $region_details){

                    $site_store_id = $region_details->site_store_id;

                    $this->logger->debug("--- Fetching Category Products For {$supermarket_name} - {$region_name}[{$site_store_id}]");

                    $category_results[] = $this->request_category_v3($category_endpoint_v3, $site_store_id, $supermarket_banner, $page_number, $size, $category_number);
                }
            }

            return $category_results;
        } catch(Exception $e){
            $this->logger->debug('V3 Category Request Error: ' . $e->getMessage());
        }

        return null;

    }

    private function request_category_v3(string $url, string $site_store_id, string $banner, int $page_number, int $size, $category_number){
        $response = $this->request_service->request($url, 'POST', [
            'pagination' => [
                'from' => $page_number,
                'size' => $size
            ],

            'banner' => $banner,
            'cartId' => '06563644-a26a-4c66-961a-e870a406e48c',
            'lang' => 'en',
            'storeId' => $site_store_id,
            'date' => date('dmY'),
            'pickupType' => 'STORE',
            'categoryId' => $category_number

        ], ['x-apikey' => $this->loblaws_config->keys->api], 300, 1);
        
        return $this->request_service->parse_json($response); 
    }

    private function add_category_products(&$products, $category_products){
        foreach($category_products as $product_data){
            $site_product_id = $product_data->productId ?? $product_data->code;
            $products[$site_product_id] = $site_product_id;
        }
    }

    private function unique_new_products(array $products, $category_id){
        $unique_products = array_values($products);

        $this->logger->debug(count($unique_products) . ' Total Unique Products Found');
        
        if(count($products) > 0){
            // Query database get all products found in database, then ignore those.
            $products_found = $this->product_model
            ->select(['site_product_id'])
            ->join('category_products', 'category_products.product_id', 'products.id')
            ->where(['category_products.child_category_id' => $category_id])
            ->where_in('site_product_id', $unique_products)
            ->group_by('site_product_id')
            ->get();

            foreach($products_found as $product){
                unset($products[$product->site_product_id]);
            }
        }

        return array_values($products);
    }

}

?>