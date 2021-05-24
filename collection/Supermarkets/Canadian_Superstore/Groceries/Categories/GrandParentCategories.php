<?php

namespace Collection\Supermarkets\Canadian_Superstore\Groceries\Categories;

use Collection\Supermarkets\Canadian_Superstore\Services\CategoryService;
use Monolog\Logger;
use Services\DatabaseService;
use Services\RememberService;

class GrandParentCategories {

    private $logger, $database_service;

    private $remember_service, $category_service;
    private $parent_categories;

    public function __construct(RememberService $remember_service, Logger $logger, DatabaseService $database_service, CategoryService $category_service, ParentCategories $parent_categories){
        $this->remember_service = $remember_service;
        $this->category_service = $category_service;

        $this->parent_categories = $parent_categories;
       
        $this->logger = $logger;
        $this->database_service = $database_service;
    }

    public function create_categories($categories){
        
        $last_category_index = $this->remember_service->get('grand_parent_category_index') ?? 0;
        
        $categories_list = array_slice($categories, $last_category_index);

        if(count($categories_list) != 0){

            $first_category = (object)$categories_list[0];

            $this->logger->notice("Starting With Grand Parent Category: [$last_category_index] " . $first_category->name);
    
            foreach($categories_list as $index => $grand_parent_category){
                $category_index = $index + $last_category_index;

                $this->remember_service->set('grand_parent_category_index', $category_index);
                
                $grand_parent_category_model = $this->category_service->select_category($grand_parent_category, 'grand_parent', $category_index);

                $this->parent_categories->create_category($grand_parent_category_model, $grand_parent_category);
            }

            $this->remember_service->set('grand_parent_category_index', 0);
        }

    }
    
}

?>