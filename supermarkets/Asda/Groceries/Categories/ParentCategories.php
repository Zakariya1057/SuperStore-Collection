<?php

namespace Supermarkets\Asda\Groceries\Categories;

class ParentCategories extends Categories {

    public function create_category($category_item){

        $child_categories = new ChildCategories($this->config, $this->logger, $this->database, $this->remember);


        $category_details = $this->select_category($category_item,'grand_parent');
        $this->logger->notice("- Parent Category: $category_details->name");

        $category_details->grand_parent_category_id = $category_details->id;

        $last_category_index = $this->remember->get('parent_category_index') ?? 0;

        $categories_list = array_slice($category_item->categories,$last_category_index);

        if(count($categories_list) != 0){
            $first_category = $categories_list[0];
            $this->logger->notice("Starting With Parent Category: [$last_category_index] " . $first_category->name);

            foreach($categories_list as $index => $department){
                $this->remember->set('parent_category_index',$index + $last_category_index);

                $child_categories->create_category($category_details, $department);
            }
            
            $this->remember->set('parent_category_index',0);

        }

    }

}

?>