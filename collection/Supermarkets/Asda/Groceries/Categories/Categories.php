<?php

namespace Collection\Supermarkets\Asda\Groceries\Categories;

use Exception;

use Models\Category\CategoryModel;
use Models\Category\ChildCategoryModel;
use Models\Category\GrandParentCategoryModel;
use Models\Category\ParentCategoryModel;
use Models\Product\ProductModel;

use Monolog\Logger;
use Services\Config;
use Services\Database;
use Services\Remember;

use Collection\Supermarkets\Asda\Asda;

class Categories extends Asda {

    public $grand_parent_category_model, $parent_category_model, $child_category_model, $product_model;

    function __construct(Config $config, Logger $logger, Database $database, Remember $remember=null)
    {
        parent::__construct($config, $logger, $database, $remember);

        $this->grand_parent_category_model = new ParentCategoryModel($this->database);
        $this->parent_category_model = new ParentCategoryModel($this->database);
        $this->child_category_model = new ChildCategoryModel($this->database);
        $this->product_model = new ProductModel($this->database);
    }
    
    public function categories($categories){
        $grand_parent_categories = new GrandParentCategories($this->config, $this->logger, $this->database, $this->remember);
        $grand_parent_categories->create_categories($categories);
    }

    public function select_category($category,$type, $index){

        $required_fields = [
            'id',
            'name'
        ];

        foreach($required_fields as $field){
            if(!property_exists($category, $field)){
                throw new Exception("$field Field Required");
            }
        }

        $site_category_id = $category->id;
        $category_name = $category->name;
        $parent_category_id = $category->parent_category_id ?? null;

        $insert_fields = [
            'name' => $category_name,
            'site_category_id' => $site_category_id,
            'parent_category_id' => $parent_category_id,
            'store_type_id' => $this->store_type_id,
            'index' => $index
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

        // 1215135760597-910000975210-1215666691670
        // Match last part to see if category exists in database. So *-1215666691670 needs to be unique.
        $search_site_category_id = null;

        preg_match('/\-*(\d+)$/',$site_category_id, $category_matches);
        if($category_matches){
            $search_site_category_id = $category_matches[1];
        } else {
            throw new Exception('Unknown Category Number Type Found: ' . $site_category_id);
        }

        $category_item = $category->like(['store_type_id' => $this->store_type_id, 'site_category_id'=> "%$search_site_category_id"])->get()[0] ?? null;


        if(!is_null($category_item)){
            $this->logger->debug($category_name . ' Category: Found In Database');
            return $category_item;
        } else {
            $this->logger->debug($category_name . ' Category: Not Found In Database');
            $category_insert_id = $category->create($insert_fields);

            $category_item = new CategoryModel();
            $category_item->id = $category_insert_id;
            $category_item->name = $category_name;
            $category_item->site_category_id = $site_category_id;
            $category_item->parent_category_id = $parent_category_id;

            return $category_item;

        }

    }

}

?>