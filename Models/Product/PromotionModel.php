<?php
 
namespace Models\Product;

use Models\Model;

class PromotionModel extends Model {

    public 
        $name,
        $quantity,
        $price,
        $for_quantity,

        $site_promotion_id,
        $url,
        $expires,
        $starts_at,
        $ends_at;

    // Promotion Id, Name, URL, expires, starts_at,ends_at
    function __construct($database=null){

        parent::__construct($database);

        $this->table('promotions');

        $fields = [
            'name' => [],

            'quantity' => [],

            'price' => [
                'nullable' => true
            ],

            'for_quantity' => [
                'nullable' => true
            ],
            
            'site_promotion_id' => [
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