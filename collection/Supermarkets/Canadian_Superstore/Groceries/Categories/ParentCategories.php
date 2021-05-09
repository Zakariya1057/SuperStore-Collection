<?php

namespace Collection\Supermarkets\Canadian_Superstore\Groceries\Categories;

use Models\Category\ChildCategoryModel;
use Models\Category\ParentCategoryModel;

class ParentCategories extends Categories {

    public function create_category($grand_parent_category_model, $grand_parent_category){

        $categories = $grand_parent_category['parent_categories'];

        $child_categories = new ChildCategories($this->config, $this->logger, $this->database, $this->remember);

        $this->logger->notice("- Parent Category: $grand_parent_category_model->name");

        $last_category_index = $this->remember->get('parent_category_index') ?? 0;

        $categories_list = array_slice($categories, $last_category_index);

        $child_category_model = new ChildCategoryModel($this->database);
        $parent_category_model = new ParentCategoryModel($this->database);

        if(count($categories_list) != 0){
            $first_category = (object)$categories_list[0];

            $this->logger->notice("Starting With Parent Category: [$last_category_index] " . $first_category->name);

            foreach($categories_list as $index => $parent_category){
                $parent_category = (object)$parent_category;

                $this->remember->set('parent_category_index',$index + $last_category_index);

                $parent_category->parent_category_id = $grand_parent_category_model->id;

                $parent_category_item = $this->select_category($parent_category,'parent');

                $child_categories->create_category($parent_category_item, $parent_category);

                
                // If all child categories deleted, delete parent category as well
                $child_categories_count = count($child_category_model->where(['parent_category_id' => $parent_category_item->id])->get());
                if($child_categories_count == 0){
                    $this->logger->notice('No Child Categories For Parent Category Found. Deleting Parent Category ID');
                    $parent_category_model->where(['id' => $parent_category_item->id])->delete();
                }
            }

            $this->remember->set('parent_category_index',0);

        }

    }

}

?>