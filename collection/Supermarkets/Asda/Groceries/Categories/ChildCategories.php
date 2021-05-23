<?php

namespace Collection\Supermarkets\Asda\Groceries\Categories;

use Exception;
use Models\Category\ChildCategoryModel;
use Collection\Supermarkets\Asda\Groceries\Products\Products;

class ChildCategories extends Categories {

    private $product;

    private function setupClasses(){
        if(is_null($this->product)){
            $this->product = new Products($this->config_service,$this->logger,$this->database_service,$this->remember_service);
        }
    }

    public function create_category($parent_category, $child_categories){

        $this->setupClasses();

        $last_category_index = $this->remember_service->get('child_category_index') ?? 0;

        $categories_list = array_slice( $child_categories, $last_category_index );

        if(count($categories_list) != 0){

            $first_category = $categories_list[0];
            $this->logger->debug("Starting With Child Category: [$last_category_index] " . $first_category->name);
    
            foreach($categories_list as $index => $child_category_data){
                
                $category_index = $index + $last_category_index;

                $site_category_id = $child_category_data->id;

                $this->logger->notice("-- Child Category: [$site_category_id] $child_category_data->name");

                $child_category_data->parent_category_id = $parent_category->id;
                $child_category = $this->select_category($child_category_data, 'child', $category_index);

                // If the site_category_id has changed from previous then update it
                if($child_category_data->id != $child_category->site_category_id){
                    $this->logger->debug("Changed Site Category ID: $site_category_id -> ". $child_category->site_category_id);
                    $child_category->site_category_id = $child_category_data->id;
                }

                $child_category->grand_parent_category_id = $parent_category->parent_category_id;

                $this->remember_service->set('child_category_index', $category_index);
                
                $this->category_products($child_category); 

            }

            $this->remember_service->set('child_category_index',0);

        } 

    }


    public function category_products($child_category){

        // Get list of all product sold on shelf. Insert new products
        $products = $this->get_category_products($child_category->site_category_id);

        $this->logger->notice('Found '.count($products).' Products For Category: '. $child_category->name);

        if(count($products) > 0){

            $last_product_index = $this->remember_service->get('product_index') ?? 0;
        
            $products = array_slice($products,$last_product_index);
    
            $first_product_name = $products[0]->name;
    
            $this->logger->notice("Starting With Product: [$last_product_index] $first_product_name");
    
            //Loop through and insert into database
            foreach($products as $index => $product_item){
    
                $this->remember_service->set('product_index', $index + $last_product_index);

                if(!is_null($product_item)){
                    $this->product->create_product($product_item->sku_id, $child_category);
                    //Between Each Products. Wait 1 Second
                    sleep(1);
                }

            }

        } else {
            $this->logger->info('No product found for category. Removing it');
            $category_model = new ChildCategoryModel($this->database_service);
            $category_model->where(['id' => $child_category->id])->delete();
        }


        $this->remember_service->set('product_index',0);
    }

    public function get_category_products($category_site_id){
        //Return an array of products with details. To be then be inserted into database
        $products = [];

        $this->logger->debug("Get Products For Category Site ID: $category_site_id");

        if($this->env == 'dev'){
            $shelf_results = file_get_contents(__DIR__."/../../data/Asda/New_Shelf.json");
            $shelf_data = $this->request_service->parse_json($shelf_results)->data->tempo_cms_content;

            $this->set_category_details($products, $shelf_data);
        } else {
            $shelf_data = $this->category_service->request_details('child_category', $category_site_id);
            $total_pages = $this->set_category_details($products, $shelf_data);

            $this->logger->debug('Category Pages: '. $total_pages);
            
            if($total_pages > 1){
                for($i = 1; $i < $total_pages; $i++){
                    $shelf_data = $this->category_service->request_details('child_category', $category_site_id, $i + 1);
                    $this->set_category_details($products, $shelf_data);
                }
            }
        }

        return $products;

    }

    private function set_category_details(&$products, $shelf_data){

        $max_pages = 0;
        $category_products_found = false;

        foreach($shelf_data->zones as $zone){
            if(key_exists('products', $zone->configs) && !is_null($zone->configs->products)){
                $category_products_found = true;

                $category_details = $zone->configs;

                $max_pages = $category_details->max_pages ?? 0;

                $items = $category_details->products->items;

                foreach($items as $product){
                    $products[] = $product->item;
                }
            }
        }

        if(!$category_products_found){
            throw new Exception('For all child category configs, no product property found. Must be an issue with payload');
        }

        return $max_pages;
    }

}

?>