<?php

namespace Collection\Supermarkets\Canadian_Superstore\Groceries\Categories;

use Exception;
use Models\Category\CategoryProductModel;
use Models\Category\ChildCategoryModel;
use Collection\Supermarkets\Canadian_Superstore\Groceries\Products\Products;

class ChildCategories extends Categories {

    public function create_category($parent_category_model, $parent_category){

        $categories = $parent_category->child_categories;

        $last_category_index = $this->remember->get('child_category_index') ?? 0;

        $grand_parent_category_id = $parent_category->parent_category_id;
        
        $categories_list = array_slice($categories, $last_category_index);

        if(count($categories_list) != 0){

            $first_category = (object)$categories_list[0];

            $this->logger->debug("Starting With Child Category: [$last_category_index] " . $first_category->name);
    
            foreach($categories_list as $index => $child_category){

                $child_category = (object)$child_category;
                $child_category->parent_category_id = $parent_category_model->id;
                $child_category_details = $this->select_category($child_category, "child");

                $child_category_details->number = $child_category->number;
                $child_category_details->grand_parent_category_id = $grand_parent_category_id;

                $this->remember->set('child_category_index',$index + $last_category_index);
                $this->category_products($child_category_details);
            }

            $this->remember->set('child_category_index',0);

        }

    }


    public function category_products($category_details){

        // Get list of all product sold on shelf. Insert new products
        $products_data = $this->category_results($category_details);

        $products = $products_data['products'];
        $request_type = $products_data['type'];

        $product_count = count($products);
        
        $this->logger->notice("Found $product_count Products For Category: {$category_details->name}");

        if($product_count > 0){

            $last_product_index = $this->remember->get('product_index') ?? 0;
        
            $products = array_slice($products, $last_product_index);
    
            //Loop through and insert into database
            foreach($products as $index => $site_product_id){
                $this->remember->set('product_index', $index + $last_product_index);
    
                $product = new Products($this->config,$this->logger,$this->database,$this->remember);
                $product->create_product($site_product_id, $category_details, $request_type);
    
                // Between Each Products. Wait 1 Second
                sleep(1);
                
            }

        } else {
            $this->logger->info('No Products Found For Category: '. $category_details->id);

            $product_categories = new CategoryProductModel($this->database);
            $products_count = count($product_categories->where(['child_category_id' => $category_details->id])->get());

            if($products_count == 0){
                $this->logger->debug('No Products Found For Matching Product Categories. Deleting Child Category');
                // Check if no products for category
                $category_model = new ChildCategoryModel($this->database);
                $category_model->where(['id' => $category_details->id])->delete();
            } else {
                $this->logger->debug('Products Found In Database For Category. Not Deleting Child Category');
            }

        }

        $this->remember->set('product_index', 0);
    }

    public function category_results($category){
        $this->logger->debug("Fetch Products For Child Category: {$category->name}");

        if($this->env == 'dev'){
            $category_data = [];
        } else {
            $category_data = $this->request_category_results($category->number);
        }
        
        return $category_data;
    }

    private function request_category_results($category_number){
        $request_type = 'v3';

        $size = 50;
        $page_number = 0;

        $products = [];

        $category_data = $this->request_category_data($category_number, $size, $page_number);

        $category_results = $category_data['results'];
        $request_type = $category_data['type'];

        $pagination_data = $category_results->pagination;
        $total_results = $pagination_data->totalResults;

        $this->add_category_products($products, $category_results->results);

        if($total_results > $size){
            $total_pages = ceil($total_results / $size);
            $this->logger->debug('Total Pages: '. $total_pages);

            for($i = 1; $i < $total_pages; $i++){
                $category_data = $this->request_category_data($category_number, $size, $i);
                $this->add_category_products($products, $category_data['results']->results);
            }

        } else {
            $this->logger->debug('Total Pages: 1');
        }

        return ['products' => $products, 'type' => $request_type];
    }

    private function request_category_data($category_number, $size = 50, $page_number = 0){

        $categories_endpoints = $this->endpoints->categories;
        $category_endpoint_v2 = $categories_endpoints->v2 . $category_number;
        $category_endpoint_v3 = $categories_endpoints->v3;

        $this->logger->debug('Category Request Page Number: '. $page_number);

        try {
            $results = $this->request->request($category_endpoint_v3, 'POST', [
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

            $request_type = 'v3';

        } catch(Exception $e){
            $request_type = 'v2';
            $results = $this->request->request($category_endpoint_v2, 'GET', [], ['Is-Pcs-Catalog' => 'true']);
        }

        $results =  $this->request->parse_json($results);

        return ['results' => $results, 'type' => $request_type];
    }

    private function add_category_products(&$products, $category_products){
        foreach($category_products as $product_data){
            $site_product_id =  $product_data->productId ?? $product_data->code;
            $products[] = $site_product_id;
        }
    }

}

?>