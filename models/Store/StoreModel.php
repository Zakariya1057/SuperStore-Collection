<?php
 
namespace Models\Store;

use Services\Loggers;
use Models\Model;

class StoreModel extends Model {

    public $name, $description, $manager, $telephone, $store_image, $uber_url, $google_url, $url, $site_store_id, $store_type_id;

    function __construct($database=null){

        parent::__construct($database);

        $this->table("stores");

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

            'store_type_id' => [
                'type' => 'int',
            ]

        ];

        $this->fields($fields);

    }

}

?>