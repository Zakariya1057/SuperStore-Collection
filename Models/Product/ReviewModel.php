<?php
// Create Model Class. Use For Inserting Into Databas. Searching And Deleting.
namespace Models\Product;

use Models\Model;

class ReviewModel extends Model {
    
    public $name,$text,$rating,$title,$user_id,$created_at,$product_id, $site_review_id;

    //ID,text,rating, product_id, user_id, created_at,updated_at
    function __construct($database=null){

        parent::__construct($database);

        $this->table("reviews");

        $fields = [
            'text' => [
                'nullable' => true,
                'max_length' => 5000
            ],

            'title' => [
                'nullable' => true,
                'max_length' => 100
            ],

            'site_review_id' => [],

            'rating' => [
                'type' => 'int',
                'range' => [
                    'min' => 0,
                    'max' => 5
                ]
            ],

            'product_id' => [
                'type' => 'int'
            ],

            'user_id' => [
                'type' => 'int'
            ]
        ];

        $this->fields($fields);

    }

}

?>