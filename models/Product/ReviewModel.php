<?php
 
namespace Models\Product;

use Models\Model;
use Services\DatabaseService;

class ReviewModel extends Model {
    
    public $name,$text,$rating,$title,$user_id,$created_at,$product_id, $site_review_id;

    function __construct(DatabaseService $database_service=null){

        parent::__construct($database_service);

        $this->table('reviews');

        $fields = [
            'text' => [
                'nullable' => true
            ],

            'title' => [
                'nullable' => true,
                'max_length' => 255
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