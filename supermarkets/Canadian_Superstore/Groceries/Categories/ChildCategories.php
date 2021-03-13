<?php

namespace Supermarkets\Canadian_Superstore\Groceries\Categories;

use Exception;
use Supermarkets\Canadian_Superstore\Groceries\Products\Products;

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

                $this->remember->set('child_category_index',$index + $last_category_index);
                $this->category_products($child_category_details, $grand_parent_category_id);
            }

            $this->remember->set('child_category_index',0);

        }

    }


    public function category_products($category_details, $grand_parent_category_id){

        // Get list of all product sold on shelf. Insert new products
        $products_data = $this->create_products($category_details);

        $products = $products_data['results'];
        $request_type = $products_data['type'];

        $product_count = count($products);
        
        $this->logger->notice("Found $product_count Products For Category: {$category_details->name}");

        if($product_count > 0){

            $last_product_index = $this->remember->get('product_index') ?? 0;
        
            $products = array_slice($products,$last_product_index);
    
            $first_product = $products[0];
    
            $this->logger->notice("Starting With Product: [$last_product_index] " . $first_product->name ?? $first_product->title);
    
            //Loop through and insert into database
            foreach($products as $index => $product_details){
    
                $this->remember->set('product_index', $index + $last_product_index);
    
                $product = new Products($this->config,$this->logger,$this->database,$this->remember);
                $product->create_product($product_details, $request_type);
    
                // Between Each Products. Wait 1 Second
                sleep(1);
                
            }

        }

    
        $this->remember->set('product_index',0);
    }

    public function create_products($category){
        $categories_endpoints = $this->endpoints->categories;

        $category_endpoint_v2 = $categories_endpoints->v2 . $category->number;
        $category_endpoint_v3 = $categories_endpoints->v3;

        $request_type = 'v3';

        $this->logger->debug("Product Categories: {$category->name}");

        if($this->env == 'dev'){
            $products_results = file_get_contents(__DIR__."/../../data/Asda/Shelf.json");
        } else {

            try {
                $products_results = $this->request->request($category_endpoint_v3, 'POST', [
                    'pagination' => [
                        'from' => 0,
                        'size' => 50
                    ],

                    'banner' => 'superstore',
                    'cartId' => '564d3383-738b-4407-b170-1064b504d991',
                    'lang' => 'en',
                    'storeId' => '2800',
                    'date' => '13032021',
                    'pickupType' => 'STORE',
                    'categoryId' => $category->number

                ], ['x-apikey' => '1im1hL52q9xvta16GlSdYDsTsG0dmyhF'], 300, 1);

            } catch(Exception $e){
                $request_type = 'v2';
                $products_results = $this->request->request($category_endpoint_v2, 'GET', [], ['Is-Pcs-Catalog' => 'true']);
            }
            
        }
        
        $products_data = $this->request->parse_json($products_results);

        return ['results' => $products_data->results, 'type' => $request_type];

    }

}

?>