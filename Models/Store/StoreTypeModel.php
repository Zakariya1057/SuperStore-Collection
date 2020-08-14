<?php
// Create Model Class. Use For Inserting Into Databas. Searching And Deleting.
namespace Models\Store;

use Models\Model;

//Each store location.
class StoreTypeModel extends Model {
    
    public $name, $description, $logo_large, $logo_medium;
    
    // Name, Logo Small, Logo Big
    function __construct($database=null){

        parent::__construct($database);

        $this->table("store_types");

        $fields = [
            'name' => [],
            'description' => [
                'nullable' => true,
                'limit' => [
                    'min' => 0,
                    'max' => 1000
                ]
                ],
            'logo_large' => [
                'limit' => [
                    'min' => 0,
                    'max' => 500
                ]
            ],
            'logo_medium' => [
                'limit' => [
                    'min' => 0,
                    'max' => 500
                ]
            ]
        ];

        $this->fields($fields);

    }

}

?>