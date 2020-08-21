<?php

namespace Stores\Asda;

use Exception;

use Models\Category\CategoryModel;
use Models\Category\ChildCategoryModel;
use Models\Category\GrandParentCategoryModel;
use Models\Category\ParentCategoryModel;

class AsdaCategories extends Asda {

    function __construct($config,$logger,$database,$remember)
    {
        parent::__construct($config,$logger,$database,$remember);
    }

    public function categories($categories){

        $last_category_index = $this->remember->get('grand_parent_category_index') ?? 0;
        
        $categories_list = array_slice($categories->categories,$last_category_index);

        if(count($categories_list) != 0){

            $first_category = $categories_list[0];
            $this->logger->notice("Starting With Grand Parent Category: [$last_category_index] " . $first_category->displayName);
    
            foreach($categories_list as $index => $category_item){
                $this->remember->set('grand_parent_category_index',$index + $last_category_index);
                $this->create_category($category_item);
            }

            $this->remember->set('grand_parent_category_index',0);
        }

    }

    public function create_category($category_item){
        //Insert or select category item.

        if(!$this->exclude_category($category_item->displayName)){
            $this->logger->debug('Category Not Excluded: '. $category_item->displayName);

            $category_details = $this->select_category($category_item,"grand_parent");
            $this->logger->notice("- Category: $category_details->name");
            
            $last_category_index = $this->remember->get('parent_category_index') ?? 0;

            $categories_list = array_slice($category_item->subcategories,$last_category_index);

            if(count($categories_list) != 0){
                $first_category = $categories_list[0];
                $this->logger->notice("Starting With Parent Category: [$last_category_index] " . $first_category->displayName);
    
                foreach($categories_list as $index => $department){
                    $this->remember->set('parent_category_index',$index + $last_category_index);
                    $this->create_department($department,$category_details->id);
                }
                
                $this->remember->set('parent_category_index',0);

            }


        } else {
            $this->logger->notice('Category Excluded: '. $category_item->displayName );
        }

    }

    public function create_department($department_item,$parent_id){
        $department_item->parent_id = $parent_id;

        $department_details = $this->select_category($department_item,"parent");
        $this->logger->notice("-- Department: $department_details->name");

        $last_category_index = $this->remember->get('child_category_index') ?? 0;

        $categories_list = array_slice($department_item->subcategories,$last_category_index);

        if(count($categories_list) != 0){

            $first_category = $categories_list[0];
            $this->logger->debug("Starting With Child Category: [$last_category_index] " . $first_category->displayName);
    
            foreach($categories_list as $index => $aisle){
                $this->remember->set('child_category_index',$index + $last_category_index);
                $this->create_aisle($aisle,$department_details->id);
            }

            $this->remember->set('child_category_index',0);

        }

    }

    public function create_aisle($aisle,$parent_id){
        $aisle->parent_id = $parent_id;

        $aisle_details = $this->select_category($aisle,"child");
        $this->logger->notice("--- Aisle: $aisle_details->name");

        $shelf = new AsdaShelves($this->config,$this->logger,$this->database,$this->remember);
        $shelf->details($aisle_details);

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