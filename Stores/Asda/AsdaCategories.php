<?php

namespace Stores\Asda;

use Exception;

use Models\Category\CategoryModel;
use Models\Category\ChildCategoryModel;
use Models\Category\GrandParentCategoryModel;
use Models\Category\ParentCategoryModel;

class AsdaCategories  extends Asda {

    function __construct($config,$logger,$database)
    {
        parent::__construct($config,$logger,$database);
    }

    public function categories($categories){
        // Go to asda page get all categories and pass to categories
        $database = $this->database;

        foreach($categories->categories as $category_item){
            $database->start_transaction();
            $this->create_category($category_item);
            $database->end_transaction();
        }

    }

    public function create_category($category_item){
        //Insert or select category item.
        $category_details = $this->select_category($category_item,"grand_parent");
        $this->logger->debug("- Category: $category_details->name");

        foreach($category_item->subcategories as $department){
            $this->create_department($department,$category_details->id);
        }

    }

    public function create_department($department_item,$parent_id){
        $department_item->parent_id = $parent_id;

        $department_details = $this->select_category($department_item,"parent");
        $this->logger->debug("-- Department: $department_details->name");

        foreach($department_item->subcategories as $aisle){
            $this->create_aisle($aisle,$department_details->id);
        }

    }

    public function create_aisle($aisle,$parent_id){
        $aisle->parent_id = $parent_id;

        $aisle_details = $this->select_category($aisle,"child");
        $this->logger->debug("--- Aisle: $aisle_details->name");

        // $shelf = new AsdaShelves($this->config,$this->logger,$this->database);
        // $shelf->details($aisle_details);

    }

    public function select_category($category,$type){
        $category_store_id = $category->dimvalid;
        $category_name = $category->displayName;
        $parent_id = $category->parent_id ?? null;

        $insert_fields = [
            'name' => $category_name,
            'site_category_id' => $category_store_id,
            'parent_id' => $parent_id,
            'site_type_id' => $this->site_type_id 
        ];

        if($type == "grand_parent"){
            $category = new GrandParentCategoryModel($this->database);
            unset($insert_fields['parent_id']);
        } elseif($type == "parent"){
            $category = new ParentCategoryModel($this->database);
        } elseif($type == "child"){
            $category = new ChildCategoryModel($this->database);
        } else {
            throw new Exception("Unknown Category Type Found: $type");
        }

        $category_item = $category->where(["site_category_id" => $category_store_id])->get();

        if($category_item){
            return $category_item;
        } else {
            $category_insert_id = $category->create($insert_fields);

            $category_item = new CategoryModel();
            $category_item->id = $category_insert_id;
            $category_item->name = $category_name;
            $category_item->site_category_id = $category_store_id;
            $category_item->parent_id = $parent_id;

            return $category_item;

        }

    }

}

?>