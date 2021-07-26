<?php
 
namespace Models\Store;

use Models\Model;
use Services\DatabaseService;

class StoreModel extends Model {

    public 
        $name, 
        $description, 

        $manager, 
        $telephone, 

        $store_image, 

        $uber_url, 
        $google_url, 

        $url, 

        $site_store_id, 
        $supermarket_chain_id,
        
        $last_checked;

    function __construct(DatabaseService $database_service=null){

        parent::__construct($database_service);

        $this->table('stores');

        $fields = [
            'name' => [],

            'description'   => [
                'nullable' => true,
                'limit' => [
                    'min' => 0,
                    'max' => 1000
                ]
            ],

            'store_image'   => [
                'nullable' => true,
                'limit' => [
                    'min' => 0,
                    'max' => 1000
                ]
            ],

            'uber_url'      => [
                'type' => 'url',
                'nullable' => true,
                'limit' => [
                    'min' => 0,
                    'max' => 1000
                ]
            ],
            'google_url'    => [
                'type' => 'url',
                'nullable' => true,
                'limit' => [
                    'min' => 0,
                    'max' => 1000
                ]
            ],
            'url'      => [
                'type' => 'url',
                'nullable' => true,
                'limit' => [
                    'min' => 0,
                    'max' => 1000
                ]
            ],

            'site_store_id' => [
                'type' => 'int',
            ],

            'manager' => [
                'nullable' => true,
            ],

            'telephone' => [
                'nullable' => true,
            ],

            'supermarket_chain_id' => [
                'type' => 'int',
            ],


            'last_checked' => [
                'ignore' => true,
                'nullable' => true
            ]

        ];

        $this->fields($fields);

    }

}

?>