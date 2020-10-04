<?php
// Create Model Class. Use For Inserting Into Databas. Searching And Deleting.
namespace Models\Product;

use Models\Model;

class PromotionModel extends Model {

    public $name,$site_promotion_id,$url,$expires,$starts_at,$ends_at;

    // Promotion Id, Name, URL, expires, starts_at,ends_at
    function __construct($database=null){

        parent::__construct($database);

        $this->table("promotions");

        $fields = [
            'name' => [],
            'site_promotion_id' => [],
            'store_type_id' => [
                'type' => 'int'
            ],
            'expires' => ['nullable' => true],
            'starts_at' => ['nullable' => true],
            'ends_at' => ['nullable' => true],
            'url' => []
        ];

        $this->fields($fields);

    }

}

?>