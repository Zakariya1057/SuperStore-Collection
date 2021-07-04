<?php

namespace Collection\Services;

use Models\Category\ProductGroupModel;
use Models\Product\ProductModel;
use Services\DatabaseService;

class SharedProductGroupService {

    private $product_group_model;

    public function __construct(DatabaseService $database_service){
        $this->product_group_model = new ProductGroupModel($database_service);
    }

    public function create(ProductModel $product_details, int $child_category_id, int $company_id): int {
        // Create/Select Product Group
        $product_group_model = clone $this->product_group_model;
        
        $product_group = $product_details->product_group;

        $site_product_group_id = $product_group->id;
        $product_group_name = $product_group->name;

        $product_group_results = $this->product_group_model
        ->where([
            'child_category_id' => $child_category_id, 
            'name' => $product_group_name
        ])->first();

        if(is_null($product_group_results)){
            // Create Product Group And Return
            $product_group_model->name = $product_group_name;
            $product_group_model->site_product_group_id = $site_product_group_id;
            $product_group_model->child_category_id = $child_category_id;
            $product_group_model->company_id = $company_id;

            $product_group_model->id = $product_group_model->save();

            return $product_group_model->id;
        } else {
            return $product_group_results->id;
        }
    }
}

?>