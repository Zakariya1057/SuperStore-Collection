<?php
 
namespace Models\Product;

use Models\Model;
use Services\DatabaseService;

class ChildNutritionModel extends Model {

    public $name, $grams, $percentage, $parent_nutrition_id;

    function __construct(DatabaseService $database_service=null){

        parent::__construct($database_service);

        $this->table('child_nutritions');

        $fields = [
            'name' => [],
            
            'grams' => [
                'nullable' => true
            ],
            'percentage' => [
                'nullable' => true
            ],

            'parent_nutrition_id' => ['type' => 'int'],
        ];

        $this->fields($fields);

    }

}

?>