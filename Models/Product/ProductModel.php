<?php
// Create Model Class. Use For Inserting Into Databas. Searching And Deleting.
namespace Models\Product;

use Models\Model;

class ProductModel extends Model {

    public 
        $name,
        $description,
        $large_image,
        $small_image, 
        $price,
        $old_price,
        $is_on_sale,
        $weight,
        $brand,
        $dietary_info,
        $allergen_info,
        $storage, 
        $promotion_id,
        $url,
        $total_reviews_count,
        $avg_rating,

        $reviews_searched,
        $recommended_searched,

        $parent_category_id,
        $store_type_id,
        $site_product_id;
    
    function __construct($database=null){

        parent::__construct($database);

        $this->table("products");

        $fields = [
            'name' => [],
            'description' => [
                'nullable'=> true
            ],
            'large_image' => [],
            'small_image' => [],

            'price' => [
                'type' => 'price'
            ],
            'old_price' => [
                'type' => 'price',
                'nullable' => true
            ],

            'is_on_sale' => [
                'nullable' => true
            ],

            'weight' => [
                'nullable' => true
            ],

            'brand' => [],

            'dietary_info' => [
                'nullable' => true
            ],
            'allergen_info' => [
                'nullable' => true
            ],

            'url' => [],

            'storage' => [
                'nullable'=> true
            ],

            'promotion_id' => [
                'nullable' => true
            ],


            'reviews_searched' => [
                'nullable' => true
            ],
            'recommended_searched' => [
                'nullable' => true
            ],


            'avg_rating' => [
                'nullable' => true,
                'type' => 'rating'
            ],

            'total_reviews_count' => [
                'nullable' => true,
                'regex'=> 'int'
            ],

            'parent_category_id' => [
                'type' => 'int'
            ],
            'store_type_id' => [
                'type' => 'int'
            ],
            'site_product_id' => [
                'type' => 'int'
            ],
        ];

        $this->fields($fields);

    }

}

?>