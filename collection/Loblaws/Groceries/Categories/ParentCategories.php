<?php

namespace Collection\Loblaws\Groceries\Categories;

use Collection\Loblaws\Services\CategoryService;
use Models\Category\ChildCategoryModel;
use Models\Category\ParentCategoryModel;
use Monolog\Logger;
use Services\DatabaseService;
use Services\RememberService;

class ParentCategories {

    private $logger, $database_service;

    private $remember_service, $category_service;
    private $child_categories;

    public function __construct(RememberService $remember_service, Logger $logger, DatabaseService $database_service, CategoryService $category_service, ChildCategories $child_categories){
        $this->remember_service = $remember_service;
        $this->category_service = $category_service;

        $this->child_categories = $child_categories;

        $this->logger = $logger;
        $this->database_service = $database_service;
    }

    public function create_category($grand_parent_category_model, $grand_parent_category){
        
        $categories = $grand_parent_category['parent_categories'];

        $this->logger->notice("- Parent Category: $grand_parent_category_model->name");

        $last_category_index = $this->remember_service->get('parent_category_index') ?? 0;

        $categories_list = array_slice($categories, $last_category_index);

        $child_category_model = new ChildCategoryModel($this->database_service);
        $parent_category_model = new ParentCategoryModel($this->database_service);

        if(count($categories_list) != 0){
            $first_category = (object)$categories_list[0];

            $this->logger->notice("Starting With Parent Category: [$last_category_index] " . $first_category->name);

            foreach($categories_list as $index => $parent_category){
                $parent_category = (object)$parent_category;

                $category_index = $index + $last_category_index;

                $this->remember_service->set('parent_category_index', $category_index);

                $parent_category->parent_category_id = $grand_parent_category_model->id;

                $parent_category_item = $this->category_service->select_category($parent_category, 'parent', $category_index);

                $this->child_categories->create_category($parent_category_item, $parent_category);
                
                // If all child categories deleted, delete parent category as well
                $child_categories_count = count($child_category_model->where(['parent_category_id' => $parent_category_item->id])->get());
                if($child_categories_count == 0){
                    $this->logger->notice('No Child Categories For Parent Category Found. Deleting Parent Category ID');
                    $parent_category_model->where(['id' => $parent_category_item->id])->delete();
                }
            }

            $this->remember_service->set('parent_category_index',0);

        }

    }

}

?>