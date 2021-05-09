<?php

namespace Collection\Supermarkets\Asda\Groceries;

use Exception;
use Models\Category\CategoryModel;
use Monolog\Logger;
use Services\Config;
use Services\Database;
use Services\Remember;
use Collection\Supermarkets\Asda\Asda;
use Collection\Supermarkets\Asda\Groceries\Categories\Categories;

class Groceries extends Asda {

    public function groceries(){
        // Go to asda page get all categories and pass to categories
        $this->logger->notice("------- Asda Groceries Start --------");
        
        $groceries = $this->groceries_details();

        $category = new Categories($this->config,$this->logger,$this->database,$this->remember);
        $category->categories($groceries);
        
        $this->logger->notice("------- Asda Groceries Complete --------");
    }

    public function groceries_details(){
        if($this->env == 'dev'){
            $groceries_response = file_get_contents(__DIR__."/../../../data/Asda/New_Groceries.json");
            $groceries_data = $this->request->parse_json($groceries_response)->data->tempo_taxonomy;;
        } else {
            $groceries_data = $this->request_details('categories');
        }

        $categories = $this->create_categories($groceries_data->categories);
        
        return $categories;

    }

    private function create_categories($categories){
        $categories_response = [];

        foreach($categories as $category){
            if(property_exists($category,'hierarchy_id')){
                $category_model = (object)[];
                $category_model->id = $category->hierarchy_id;
                $category_model->name = $category->taxonomy_name;
                $category_model->categories = [];
                
                $this->create_category($category_model, $category);

                $categories_response[] = $category_model;
            }
        }  
        
        return $categories_response;
    }

    private function create_category($parent_category_model, $category){
        if(property_exists($category,'hierarchy_id')){
            if(property_exists($category, 'child_taxonomies')){
                foreach($category->child_taxonomies as $child_category){
                    if(property_exists($child_category,'hierarchy_id')){
                        $child_category_model = (object)[];
                        $child_category_model->id = $child_category->hierarchy_id;
                        $child_category_model->name = $child_category->taxonomy_name;
                        $child_category_model->categories = [];
    
                        $this->create_category($child_category_model, $child_category);

                        $parent_category_model->categories[] = $child_category_model;
                    }
                }
            } else {
                $child_category_model = (object)[];
                $child_category_model->id = $category->hierarchy_id;
                $child_category_model->name = $category->taxonomy_name;
                $child_category_model->categories = [];
                $parent_category_model->categories[] = $child_category_model;
            }
        }
    }

}

?>