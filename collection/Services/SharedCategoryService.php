<?php

namespace Collection\Services;

use Models\Category\CategoryProductModel;
use Services\DatabaseService;

class SharedCategoryService {

    private $database_service, $category_product;

    public function __construct(DatabaseService $database_service){
        $this->database_service = $database_service;
        $this->category_product = new CategoryProductModel($this->database_service);
    }

    public function category_exists($category_details, $product_id){
        return !is_null($this->category_product->where(['product_id' => $product_id, 'child_category_id' => $category_details->id])->get()[0] ?? null);
    }

    public function create($category_details, $product_id, $product_group_id){
        $category_product = clone $this->category_product;

        $category_product->product_id = $product_id;
        $category_product->product_group_id = $product_group_id;
        $category_product->child_category_id = $category_details->id;
        $category_product->parent_category_id = $category_details->parent_category_id;
        $category_product->grand_parent_category_id = $category_details->grand_parent_category_id;
        $category_product->insert_ignore = true;

        $category_product->save();
    }

    public function update($category_details, $product_id, $product_group_id){
        $this->category_product->where([
        'product_id' => $product_id, 
        'child_category_id' => $category_details->id
        ])
        ->update([
            'product_group_id' => $product_group_id
        ]);
    }
    
}

?>