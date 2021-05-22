<?php

namespace Models\Shared;

use Models\Model;
use Services\DatabaseService;
class GroceryListItemModel extends Model {

    public $product_id, $user_id;
    
    function __construct(DatabaseService $database_service=null){

        parent::__construct($database_service);

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