<?php

namespace Collection\Supermarkets\Asda\Groceries\Categories;

class ParentCategories extends Categories {

    public function create_category($grand_parent_category, $parent_categories){

        $child_categories = new ChildCategories($this->config, $this->logger, $this->database, $this->remember);

        $last_category_index = $this->remember->get('parent_category_index') ?? 0;

        $categories_list = array_slice($parent_categories, $last_category_index);

        if(count($categories_list) != 0){
            $first_category = $categories_list[0];
            $this->logger->notice("Starting With Parent Category: [$last_category_index] " . $first_category->name);

            foreach($categories_list as $index => $parent_category_data){
                $this->logger->notice("- Parent Category: $parent_category_data->name");
                $this->remember->set('parent_category_index',$index + $last_category_index);

                $parent_category_data->parent_category_id = $grand_parent_category->id;
                $parent_category = $this->select_category($parent_category_data ,'parent');

                $parent_category->parent_category_id = $grand_parent_category->id;

                $child_categories->create_category($parent_category, $parent_category_data->categories);


                // If all child categories deleted, delete parent category as well
                $child_categories_count = count($this->child_category_model->where(['parent_category_id' => $parent_category->id])->get());
                if($child_categories_count == 0){
                    $this->logger->notice('No Child Categories For Parent Category Found. Deleting Parent Category ID');
                    $this->parent_category_model->where(['id' => $parent_category->id])->delete();
                }

            }
            
            $this->remember->set('parent_category_index',0);

        }

    }

}

?>