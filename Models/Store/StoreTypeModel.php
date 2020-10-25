<?php
// Create Model Class. Use For Inserting Into Databas. Searching And Deleting.
namespace Models\Store;

use Models\Model;

//Each store location.
class StoreTypeModel extends Model {
    
    public $name, $description, $large_logo, $small_logo, $user_id;
    
    // Name, Logo Small, Logo Big
    function __construct($database=null){

        parent::__construct($database);

        $this->table("store_types");

        $fields = [
            'id' => [
                'type' => 'int'
            ],
            
            'name' => [],
            'description' => [
                'nullable' => true,
                'limit' => [
                    'min' => 0,
                    'max' => 1000
                ]
                ],
            'large_logo' => [
                'limit' => [
                    'min' => 0,
                    'max' => 500
                ]
            ],
            'small_logo' => [
                'limit' => [
                    'min' => 0,
                    'max' => 500
                ]
            ],

            'user_id' => [
                'type' => 'int'
            ],

        ];

        $this->fields($fields);

    }

}

?>