<?php

namespace Collection\Services;

use Services\DatabaseService;

class SharedNutritionService {

    private $database_service;

    public function __construct(DatabaseService $database_service){
        $this->database_service = $database_service;
    }

    public function create_nutritions(int $product_id, $product){
        foreach($product->nutritions as $nutrition){
            $nutrition->product_id = $product_id;
            $nutrition_id = $nutrition->save();
            $this->create_child_nutritions($nutrition_id, $nutrition->child_nutritions);
        }
    }

    public function create_child_nutritions(int $parent_nutrition_id, $child_nutritions){
        foreach($child_nutritions as $nutrition){
            $nutrition->parent_nutrition_id = $parent_nutrition_id;
            $nutrition->save();
        }
    }

}

?>