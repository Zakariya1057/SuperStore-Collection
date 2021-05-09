<?php

namespace Collection\Services;

use Exception;
use Models\Category\CategoryProductModel;
use Monolog\Logger;
use Services\Database;
use Collection\Supermarkets\Canadian_Superstore\CanadianSuperstore;

class CategoryService extends CanadianSuperstore {

    private $category_product;

    private function setupCategoryProductModel(){
        if(is_null($this->category_product)){
            $this->category_product = new CategoryProductModel($this->database);
        }
    }

    public function category_exists($category_details, $product_id){
        $this->setupCategoryProductModel();

        return !is_null($this->category_product->where(['product_id' => $product_id, 'child_category_id' => $category_details->id])->get()[0] ?? null);
    }

    public function create($category_details, $product_id, $product_group_id){
        $this->setupCategoryProductModel();

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
        $this->setupCategoryProductModel();

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