<?php
 
namespace Models\Store;

use Models\Model;
use Services\DatabaseService;

class SupermarketChainModel extends Model {
    
    public $id, $name, $currency, $description, $large_logo, $small_logo, $user_id;
    
    function __construct(DatabaseService $database_service=null){

        parent::__construct($database_service);

        $this->table('supermarket_chains');

        $fields = [
            'name' => [],

            'currency' => [],

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