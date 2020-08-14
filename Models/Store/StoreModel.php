<?php
// Create Model Class. Use For Inserting Into Databas. Searching And Deleting.
namespace Models\Store;

use Shared\Loggers;
use Models\Model;

//Each store location.
class StoreModel extends Model {
    
    // Name, Phone Number, Fuel Level, Type(Asda),URL Store Id, 24Hours, Store Image Google Thumbnails Locations, Opening Hours, All Facilities,Address, Longitude + Latitude, Google Maps, Uber,

    // Facilities Table - Facilities For Each Store
    // Stores Table - Main Details
    // Opening Hours - From Monday To Sunday
    // Locations Table - Address 1, Address 2, Address 3, Long, Lat

    public $name, $description, $store_image, $uber_url, $google_url, $site_url, $store_site_id, $store_type_id;

    //Name, Store Type, Google Store Image, Google Maps URl, Uber URL
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
            'site_url'      => [
                'type' => 'url',
                'nullable' => true,
                'limit' => [
                    'min' => 0,
                    'max' => 1000
                ]
            ],


            'store_site_id' => [
                'type' => 'int',
            ],

            'store_type_id' => [
                'type' => 'int',
            ]

        ];

        $this->fields($fields);

    }

}

?>