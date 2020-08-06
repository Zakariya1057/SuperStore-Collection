<?php

namespace Stores\Asda;

use Shared\Database;
use Shared\Request;
use Models\CategoryModel;

class AsdaCategories {

    private $logger,$request,$config,$database;

    function __construct($config,$logger,$database){
        $this->request = new AsdaRequests($config,$logger);
        $this->database = $database;
        $this->logger  = $logger;
        $this->config  = $config;
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
        $category_details = $this->category_information($category_item);
        $this->logger->debug("- Category: $category_details->name");

        foreach($category_item->subcategories as $department){
            $this->create_department($department);
        }

    }

    public function create_department($department_item){
        $department_details = $this->category_information($department_item);
        $this->logger->debug("-- Department: $department_details->name");

        foreach($department_item->subcategories as $aisle){
            $this->create_aisle($aisle);
        }

    }

    public function create_aisle($aisle){
        $aisle_details = $this->category_information($aisle);
        $this->logger->debug("--- Aisle: $aisle_details->name");

        $shelf = new AsdaShelves($this->config,$this->logger,$this->database);
        $shelf->details($aisle_details);

        // foreach($aisle->subcategories as $shelf){
        //     $this->create_shelf($shelf);
        // }

    }

    // public function create_shelf($shelf){
    //     $shelf_details = $this->category_information($shelf);
    //     $this->logger->debug("\t\t\tShelf: $shelf_details->name");
        
    //     $asda_shelf = new AsdaShelf($this->config,$this->logger);
    //     $asda_shelf->products($shelf);
    // }

    public function category_information($category){
        $category_store_id = $category->dimvalid;
        $category_name = $category->displayName;

        $category = new CategoryModel($this->database);

        // $category_item = $category->where(["category_store_id" => $category_store_id]);
        $category_item = null;

        if(is_null($category_item)){
            $category_insert_id = $category->create(['store_category_id' => $category_store_id,'name' => $category_name]);

            $category_item = new CategoryModel();
            $category_item->store_category_id = $category_store_id;
            $category_item->name = $category_name;
            $category_item->id = $category_insert_id;
        } 

        return $category_item;

    }

}

?>