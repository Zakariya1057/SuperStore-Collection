<?php

namespace Collection\Supermarkets\Canadian_Superstore\Groceries\Categories;

use Exception;

use Models\Category\CategoryModel;
use Models\Category\ChildCategoryModel;
use Models\Category\GrandParentCategoryModel;
use Models\Category\ParentCategoryModel;

use Collection\Supermarkets\Canadian_Superstore\CanadianSuperstore;

class Categories extends CanadianSuperstore {

    public function categories($categories){
        $grand_parent_categories = new GrandParentCategories($this->config, $this->logger, $this->database, $this->remember);
        $grand_parent_categories->create_categories($categories);
    }

    public function select_category($category,$type){

        $category = (object)$category;

        $category_name = $category->name;
        $parent_category_id = $category->parent_category_id ?? null;
        $category_number = $category->number ?? null;

        $insert_fields = [
            'name' => $category_name,
            'site_category_id' => $category_number,
            'parent_category_id' => $parent_category_id,
            'store_type_id' => $this->store_type_id 
        ];

        if($type == 'grand_parent'){
            $category = new GrandParentCategoryModel($this->database);
            unset($insert_fields['parent_category_id']);
        } elseif($type == 'parent'){
            $category = new ParentCategoryModel($this->database);
        } elseif($type == 'child'){
            $category = new ChildCategoryModel($this->database);
        } else {
            throw new Exception("Unknown Category Type Found: $type");
        }

        $category_item = $category->where(['store_type_id' => $this->store_type_id, 'site_category_id' => $category_number])->get()[0] ?? null;

        if(!is_null($category_item)){
            $this->logger->debug($category_name . ' Category: Found In Database');
            return $category_item;
        } else {
            $this->logger->debug($category_name . ' Category: Not Found In Database');
            $category_insert_id = $category->create($insert_fields);

            $category_item = new CategoryModel();
            $category_item->id = $category_insert_id;
            $category_item->name = $category_name;
            $category_item->site_category_id = $category_number;
            $category_item->parent_category_id = $parent_category_id;

            return $category_item;

        }

    }

}

?>