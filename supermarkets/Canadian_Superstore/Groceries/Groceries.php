<?php

namespace Supermarkets\Canadian_Superstore\Groceries;

use Exception;

use Supermarkets\Canadian_Superstore\Groceries\Categories\Categories;
use Supermarkets\Canadian_Superstore\CanadianSuperstore;

class Groceries extends CanadianSuperstore {

    private $header_token = "JoEeGjT-gSp5ExLPhRnA1o-ARRuF3gcp2kSAGqGk7D0";

    public function create_groceries(){
        // Go to superstore page get all categories and pass to categories
        $this->logger->notice("------- Real Canadian Superstore Groceries Start --------");
        
        $groceries = $this->grocery_categories();

        $category = new Categories($this->config,$this->logger,$this->database,$this->remember);
        $category->categories($groceries);
        
        $this->logger->notice("------- Real Canadian Superstore Groceries Complete --------");
    }

    private function grocery_categories(){

        $groceries_endpoint = $this->endpoints->groceries;

        if($this->env == 'dev'){
            $groceries_response = file_get_contents(__DIR__."/../../../data/Canadian_Superstore/Groceries.json");
        } else {
            $groceries_response = $this->request->request($groceries_endpoint, 'GET', [], ['authorization' => 'Bearer ' . $this->header_token]);
        }

        $grocery_data = $this->request->parse_json($groceries_response);

        $categories = $this->grand_parent_categories($grocery_data);

        return array_values($categories);
    }


    private function grand_parent_categories($grocery_data){

        $category_items = $grocery_data->includes->Entry;

        $parent_category_items = [];
        $parent_category_mapping = [];

        $categories = [];

        $acceptable_categories = [
            // '27988' => 'Pet Supplies',
            // '27985' => 'Food',
            // '27987' => 'Baby',
            '27995' => 'Lawn, Garden & Patio',
            // '27986' => 'Home & Living',
            // '27990' => 'Toys, Games & Hobbies',
            // '27992' => 'Computers & Electronics',
            // '27991' => 'Office & School Supplies',
            // '27994' => 'Health & Beauty',
        ];

        foreach($category_items as $category){

            $fields = $category->fields;

            $details = $this->category_details($category);

            if($details){

                $category_id = $details['id'];
                $category_name = $details['name'];
                $category_number = $details['number'];

                // Grand Parent Category
                if(key_exists($category_number, $acceptable_categories)){
                    // $this->logger->debug('--- Grand Parent Category: '. $category_name);

                    $details['parent_categories'] = [];

                    $categories[$category_id] = $details;

                    foreach($fields->children as $parent_category){
                        $parent_category_mapping[$parent_category->sys->id] = $category_id;
                    }
                } else {
                    $parent_category_items[] = $category;
                }

            }

        }

        return $this->parent_categories($categories, $parent_category_items, $parent_category_mapping);

    }

    private function parent_categories($categories, $parent_category_items, $parent_category_mapping){

        $child_category_items = [];
        $child_category_mapping = [];

        foreach($parent_category_items as $category){

            $fields = $category->fields;
            
            $details = $this->category_details($category);

            if($details){

                $category_id = $details['id'];
                $category_name = $details['name'];

                if(key_exists($category_id, $parent_category_mapping)){
                    
                    // $this->logger->debug('-- Parent Category: '. $category_name);

                    $id = $parent_category_mapping[ $category_id ];

                    $details['child_categories'] = [];

                    $categories[$id]['parent_categories'][] = $details;

                    if(key_exists('children', $fields)){

                        foreach($fields->children as $child_category){

                            $parent_category_index = count($categories[$id]['parent_categories']) - 1;

                            $child_category_mapping[$child_category->sys->id] = [
                                'grand_parent_category_id' => $id,
                                'parent_category_index' => $parent_category_index,
                            ];
                        }

                    }

                } else {
                    $child_category_items[] = $category;
                }

            }

        }

        return $this->child_categories($categories, $child_category_items, $child_category_mapping);

    }

    private function child_categories($categories, $child_category_items, $child_category_mapping){

        foreach($child_category_items as $category){

            $details = $this->category_details($category);

            if($details){

                $category_id = $details['id'];
                $category_name = $details['name'];
                
                if(key_exists($category_id, $child_category_mapping)){
                    // $this->logger->debug('- Child Category: '. $category_name);

                    $mapping_details = $child_category_mapping[ $category_id ];

                    $grand_parent_category_id = $mapping_details['grand_parent_category_id'];
                    $parent_category_index = $mapping_details['parent_category_index'];
    
                    $categories[$grand_parent_category_id]['parent_categories'][$parent_category_index]['child_categories'][] = $details;
                }

            }
        }

        foreach($categories as $key => $grand_parent_categories){
            foreach($grand_parent_categories['parent_categories'] as $index => $category){
                $categories[$key]['parent_categories'][$index]['child_categories'][] = [
                    'id' => $category['id'],
                    'name' => 'View All '. $category['name'],
                    'url' => $category['url'],
                    'number' => $category['number']
                ];
            }
        }

        return $categories;

    }

    private function category_details($category): ?array {

        $fields = $category->fields;
        $sys = $category->sys;

        $details = [
            'id' => $sys->id ?? null,
            'name' => $fields->label ?? null,
            'url' => $fields->url ?? null
        ];

        preg_match('/(\d+)$/', $details['url'], $matches);

        if(!$matches){
            return null;
        } 

        $details['number'] = $matches[1];

        return $details;
    }

}

?>