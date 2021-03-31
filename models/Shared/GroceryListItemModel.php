<?php

namespace Models\Shared;

use Models\Model;

class GroceryListItemModel extends Model {

    public $product_id, $user_id;
    
    function __construct($database=null){

        parent::__construct($database);

        $this->table('grocery_list_items');

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