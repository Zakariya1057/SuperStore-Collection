<?php
 
namespace Models\Store;

use Models\Model;
use Services\DatabaseService;
class LocationModel extends Model {

    public 
        $store_id, 
        
        $city, 
        $postcode, 

        $address_line1,
        $address_line2,
        $address_line3,

        $country, 
        $region, 

        $latitude,
        $longitude;

    function __construct(DatabaseService $database_service=null){

        parent::__construct($database_service);

        $this->table('store_locations');

        $fields = [
            'store_id' => [
                'type' => 'int'
            ],

            'country' => [],

            'region' => [
                'nullable' => true
            ],

            'city' => [],

            'address_line1' => [
                'limit' => [
                    'min' => 0,
                    'max' => 1000
                ]
            ],
            'address_line2' => [
                'nullable' => true,
                'limit' => [
                    'min' => 0,
                    'max' => 1000
                ]
            ],
            'address_line3' => [
                'nullable' => true,
                'limit' => [
                    'min' => 0,
                    'max' => 1000
                ]
            ],

            'postcode' => [],

            'latitude' => [
                'nullable' => true,
                'type' => 'long_lat'
            ],

            'longitude' => [
                'nullable' => true,
                'type' => 'long_lat'
            ]
        ];

        $this->fields($fields);

    }

}

?>