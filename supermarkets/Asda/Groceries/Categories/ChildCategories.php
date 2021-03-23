<?php

namespace Supermarkets\Asda\Groceries\Categories;

use Exception;
use Supermarkets\Asda\Groceries\Products\Products;

class ChildCategories extends Categories {

    public function create_category($parent_category, $department){

        $department->parent_category_id = $parent_category->id;
        $grand_parent_category_id = $parent_category->grand_parent_category_id;


        $parent_category_details = $this->select_category($department ,'parent');

        $this->logger->notice("-- Child Category: $parent_category_details->name");

        $last_category_index = $this->remember->get('child_category_index') ?? 0;

        $categories_list = array_slice( $department->categories, $last_category_index );

        if(count($categories_list) != 0){

            $first_category = $categories_list[0];
            $this->logger->debug("Starting With Child Category: [$last_category_index] " . $first_category->name);
    
            foreach($categories_list as $index => $aisle){

                $aisle->parent_category_id = $parent_category_details->id;

                $child_category_details = $this->select_category($aisle, "child");

                $this->remember->set('child_category_index',$index + $last_category_index);
                $this->category_products($child_category_details, $grand_parent_category_id);
            }

            $this->remember->set('child_category_index',0);

        }

    }


    public function category_products($category_details, $grand_parent_category_id){

        // Get list of all product sold on shelf. Insert new products
        $products = $this->create_products($category_details->site_category_id);

        $this->logger->notice('Found '.count($products).' Products For Category: '. $category_details->name);

        $last_product_index = $this->remember->get('product_index') ?? 0;
        
        $products = array_slice($products,$last_product_index);

        $first_product = $products[0];

        $this->logger->notice("Starting With Product: [$last_product_index] $first_product");

        //Loop through and insert into database
        foreach($products as $index => $product_item){

            $this->remember->set('product_index', $index + $last_product_index);

            $product = new Products($this->config,$this->logger,$this->database,$this->remember);
            $product->create_product($product_item, $grand_parent_category_id, $category_details->parent_category_id, $category_details->id);

            //Between Each Products. Wait 1 Second
            sleep(1);
            
        }

        $this->remember->set('product_index',0);
    }

    public function create_products($site_shelf_id){
        //Return an array of products with details. To be then be inserted into database
        $products = [];

        $this->logger->debug("Shelf: $site_shelf_id");

        if($this->env == 'dev'){
            $shelf_results = file_get_contents(__DIR__."/../../data/Asda/New_Shelf.json");
            $shelf_data = $this->request->parse_json($shelf_results)->data->tempo_cms_content;
        } else {
            $shelf_data = $this->request_details('child_category', $site_shelf_id);
        }
        
        foreach($shelf_data->zones[1]->configs->products->items as $product){
            $item = $product->item;
            $products[] = $item->sku_id;
        }

        return $products;

    }

}

?>