<?php

namespace Supermarkets\Asda\Groceries\Categories;

use Exception;
use Models\Category\CategoryModel;
use Models\Category\CategoryProductModel;
use Models\Category\ChildCategoryModel;
use Models\Category\GrandParentCategoryModel;
use Models\Category\ParentCategoryModel;
use Monolog\Logger;
use Services\Config;
use Services\Database;
use Services\Remember;
use Supermarkets\Asda\Asda;

class Categories extends Asda {

    public $departments;

    function __construct(Config $config, Logger $logger, Database $database, Remember $remember=null){
        parent::__construct($config,$logger,$database,$remember);
    }

    public function categories($categories){

        $this->departments = new Departments($this->config,$this->logger,$this->database,$this->remember);

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
        if($this->exclude_service->include_category($category_item->displayName)){
            $this->logger->debug('Category Not Excluded: '. $category_item->displayName);

            $category_details = $this->select_category($category_item,'grand_parent');
            $this->logger->notice("- Category: $category_details->name");
            
            $last_category_index = $this->remember->get('parent_category_index') ?? 0;

            $categories_list = array_slice($category_item->subcategories,$last_category_index);

            if(count($categories_list) != 0){
                $first_category = $categories_list[0];
                $this->logger->notice("Starting With Parent Category: [$last_category_index] " . $first_category->displayName);
    
                foreach($categories_list as $index => $department){
                    $this->remember->set('parent_category_index',$index + $last_category_index);
                    // $this->create_department($department,$category_details->id);
                    $this->departments->create($department,$category_details->id);
                }
                
                $this->remember->set('parent_category_index',0);

            }
        } else {
            $this->logger->notice('Category Excluded: '. $category_item->displayName );
        }

    }

    public function select_category($category,$type){
        $category_store_id = $category->dimvalid;
        $category_name = $category->displayName;
        $parent_category_id = $category->parent_category_id ?? null;

        $insert_fields = [
            'name' => $category_name,
            'site_category_id' => $category_store_id,
            'parent_category_id' => $parent_category_id,
            'store_type_id' => $this->store_type_id 
        ];

        if($type == "grand_parent"){
            $category = new GrandParentCategoryModel($this->database);
            unset($insert_fields['parent_category_id']);
        } elseif($type == "parent"){
            $category = new ParentCategoryModel($this->database);
        } elseif($type == "child"){
            $category = new ChildCategoryModel($this->database);
        } else {
            throw new Exception("Unknown Category Type Found: $type");
        }

        $category_item = $category->where(["site_category_id" => $category_store_id])->or_where(['name' => $category_name])->get()[0] ?? null;

        if(!is_null($category_item)){
            $this->logger->debug($category_name . ' Category: Found In Database');
            return $category_item;
        } else {
            $this->logger->debug($category_name . ' Category: Not Found In Database');
            $category_insert_id = $category->create($insert_fields);

            $category_item = new CategoryModel();
            $category_item->id = $category_insert_id;
            $category_item->name = $category_name;
            $category_item->site_category_id = $category_store_id;
            $category_item->parent_category_id = $parent_category_id;

            return $category_item;

        }

    }

}

?>