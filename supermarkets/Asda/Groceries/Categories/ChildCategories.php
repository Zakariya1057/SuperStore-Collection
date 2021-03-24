<?php

namespace Supermarkets\Asda\Groceries\Categories;

use Exception;
use Models\Category\ChildCategoryModel;
use Supermarkets\Asda\Groceries\Products\Products;

class ChildCategories extends Categories {

    public function create_category($parent_category, $child_categories){

        $last_category_index = $this->remember->get('child_category_index') ?? 0;

        $categories_list = array_slice( $child_categories, $last_category_index );

        if(count($categories_list) != 0){

            $first_category = $categories_list[0];
            $this->logger->debug("Starting With Child Category: [$last_category_index] " . $first_category->name);
    
            foreach($categories_list as $index => $child_category_data){
                
                $this->logger->notice("-- Child Category: $child_category_data->name");

                $child_category_data->parent_category_id = $parent_category->id;
                $child_category = $this->select_category($child_category_data, "child");
                $child_category->grand_parent_category_id = $parent_category->parent_category_id;

                $this->remember->set('child_category_index',$index + $last_category_index);
                $this->category_products($child_category); 

            }

            $this->remember->set('child_category_index',0);

        } 

    }


    public function category_products($child_category){

        // Get list of all product sold on shelf. Insert new products
        $products = $this->get_category_products($child_category->site_category_id);

        $this->logger->notice('Found '.count($products).' Products For Category: '. $child_category->name);

        if(count($products) > 0){

            $last_product_index = $this->remember->get('product_index') ?? 0;
        
            $products = array_slice($products,$last_product_index);
    
            $first_product = $products[0];
    
            $this->logger->notice("Starting With Product: [$last_product_index] $first_product");
    
            //Loop through and insert into database
            foreach($products as $index => $product_item){
    
                $this->remember->set('product_index', $index + $last_product_index);
    
                $product = new Products($this->config,$this->logger,$this->database,$this->remember);
                $product->create_product($product_item, $child_category);
    
                //Between Each Products. Wait 1 Second
                sleep(1);
                
            }

        } else {
            $this->logger->info('No product found for category. Removing it');
            $category_model = new ChildCategoryModel($this->database);
            $category_model->where(['id' => $child_category->id])->delete();
        }


        $this->remember->set('product_index',0);
    }

    public function get_category_products($category_site_id){
        //Return an array of products with details. To be then be inserted into database
        $products = [];

        $this->logger->debug("Get Products For Category Site ID: $category_site_id");

        if($this->env == 'dev'){
            $shelf_results = file_get_contents(__DIR__."/../../data/Asda/New_Shelf.json");
            $shelf_data = $this->request->parse_json($shelf_results)->data->tempo_cms_content;
        } else {
            $shelf_data = $this->request_details('child_category', $category_site_id);
        }
        
        foreach($shelf_data->zones[1]->configs->products->items as $product){
            $item = $product->item;
            $products[] = $item->sku_id;
        }

        return $products;

    }

}

?>