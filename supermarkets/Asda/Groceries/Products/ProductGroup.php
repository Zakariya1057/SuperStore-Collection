<?php

namespace Supermarkets\Asda\Groceries\Products;

use Exception;
use Models\Category\ProductGroupModel;
use Monolog\Logger;
use Supermarkets\Asda\Asda;

class ProductGroup extends Asda {
    public function create($product_details, $child_category_id){
        // Create/Select Product Group
        $product_group_model = new ProductGroupModel($this->database);

        $site_product_group_id = $product_details->taxonomy_info->shelf_id;
        $product_group_name = $product_details->taxonomy_info->shelf_name;

        $product_group_results = $product_group_model
        ->where([
            'child_category_id' => $child_category_id, 
            'name' => $product_group_name
        ])->get()[0] ?? null;

        if(is_null($product_group_results)){
            // Create Product Group And Return
            $product_group_model->name = $product_group_name;
            $product_group_model->site_product_group_id = $site_product_group_id;
            $product_group_model->child_category_id = $child_category_id;
            $product_group_model->store_type_id = $this->store_type_id;

            $product_group_model->id = $product_group_model->save();

            return $product_group_model;
        } else {
            return $product_group_results;
        }

    }
}

?>