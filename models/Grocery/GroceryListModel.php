<?php

namespace Models\Grocery;

use Models\Model;
use Services\DatabaseService;

class GroceryListModel extends Model {

    public $product_id, $user_id;
    
    function __construct(DatabaseService $database_service=null){

        parent::__construct($database_service);

        $this->table('grocery_lists');

        $fields = [
            'product_id' => [
                'type' => 'int'
            ],

            'user_id' => [
                'type' => 'int'
            ],
        ];

        $this->fields($fields);

    }

}

?>