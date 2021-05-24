<?php
 
namespace Models\Product;

use Models\Model;
use Services\DatabaseService;

class PromotionModel extends Model {

    public 
        $name,
        $title, 

        $quantity,
        $price,
        $for_quantity,

        $minimum,
        $maximum,

        $region_id,
        $site_category_id,

        $store_type_id,

        $site_promotion_id,
        $url,
        $expires,
        $starts_at,
        $ends_at;

    function __construct(DatabaseService $database_service=null){

        parent::__construct($database_service);

        $this->table('promotions');

        $fields = [
            'name' => [],

            'title' => [
                'nullable' => true
            ],

            'quantity' => [
                'nullable' => true,
            ],

            'price' => [
                'nullable' => true
            ],

            'for_quantity' => [
                'nullable' => true
            ],
            
            'minimum' => [
                'nullable' => true
            ],

            'maximum' => [
                'nullable' => true
            ],

            'site_promotion_id' => [
                'nullable' => true
            ],

            'region_id' => [],

            'site_category_id' => [
                'nullable' => true
            ],

            'store_type_id' => [
                'type' => 'int'
            ],

            'expires' => ['nullable' => true],

            'starts_at' => ['nullable' => true],

            'ends_at' => ['nullable' => true],

            'url' => [
                'nullable' => true
            ]
        ];

        $this->fields($fields);

    }

}

?>