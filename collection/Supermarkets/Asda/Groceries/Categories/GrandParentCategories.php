<?php

namespace Collection\Supermarkets\Asda\Groceries\Categories;

class GrandParentCategories extends Categories {

    public function create_categories($categories){

        $parent_categories = new ParentCategories($this->config, $this->logger, $this->database, $this->remember);
        
        $last_category_index = $this->remember->get('grand_parent_category_index') ?? 0;
        
        $categories_list = array_slice($categories,$last_category_index);

        if(count($categories_list) != 0){

            $first_category = $categories_list[0];

            $this->logger->notice("Starting With Grand Parent Category: [$last_category_index] " . $first_category->name);
    
            foreach($categories_list as $index => $grand_parent_category_data){
                $grand_parent_category = $this->select_category($grand_parent_category_data,'grand_parent', $index);

                $this->remember->set('grand_parent_category_index',$index + $last_category_index);
                $parent_categories->create_category($grand_parent_category, $grand_parent_category_data->categories);
            }

            $this->remember->set('grand_parent_category_index',0);
        }

    }
    
}

?>