<?php
 
namespace Models\Product;

use Models\Model;
use Services\DatabaseService;

class NutritionModel extends Model {

    public $name, $grams, $percentage, $product_id;

    function __construct(DatabaseService $database_service=null){

        parent::__construct($database_service);

        $this->table('nutritions');

        $fields = [
            'name' => [],
            
            'grams' => [
                'nullable' => true
            ],
            'percentage' => [
                'nullable' => true
            ],

            'product_id' => ['type' => 'int'],
        ];

        $this->fields($fields);

    }

}

?>