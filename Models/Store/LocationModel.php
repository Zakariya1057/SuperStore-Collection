<?php
// Create Model Class. Use For Inserting Into Databas. Searching And Deleting.
namespace Models\Store;

use Shared\Loggers;
use Models\Model;

//Each store location.
class LocationModel extends Model {

    public $store_id, $address_line1,$address_line2,$address_line3,$latitude,$longitude;

    function __construct($database=null){

        parent::__construct($database);

        $this->table("locations");

        $fields = [
            'store_id' => [
                'type' => 'int'
            ],

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