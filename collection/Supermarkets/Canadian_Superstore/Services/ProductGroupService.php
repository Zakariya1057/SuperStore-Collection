<?php

namespace Collection\Supermarkets\Canadian_Superstore\Services;

use Models\Category\ProductGroupModel;
use Collection\Supermarkets\Canadian_Superstore\CanadianSuperstore;

class ProductGroupService extends CanadianSuperstore {

    private $product_group_model;

    private function setupProductGroupModel(){
        if(is_null($this->product_group_model)){
            $this->product_group_model = new ProductGroupModel($this->database);
        }
    }

    public function create($product_details, $child_category_id){
        // Create/Select Product Group
        $this->setupProductGroupModel();

        $product_group_model = clone $this->product_group_model;
        
        $product_group = $product_details->product_group;

        $site_product_group_id = $product_group->id;
        $product_group_name = $product_group->name;

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

            return $product_group_model->id;
        } else {
            return $product_group_results->id;
        }
    }
}

?>