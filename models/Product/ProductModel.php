<?php
 
namespace Models\Product;

use Models\Model;
use Services\DatabaseService;

class ProductModel extends Model {

    public 
        $name,

        $availability_type, 
        
        $description,
        $features,
        $dimensions,

        $country_of_origin,

        $return_policy,
        $warning,

        $large_image,
        $small_image, 

        $price,
        $old_price,
        $is_on_sale,
        $sale_ends_at,

        $currency,

        $weight,

        $serving_size,
        $household_serving_size,

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

        $company_id,
        $site_product_id,
        
        $last_checked;
    
    function __construct(DatabaseService $database_service=null){

        parent::__construct($database_service);

        $this->table('products');

        $fields = [
            'name' => [],
            
            'description' => [
                'nullable'=> true,
            ],
            
            'features' => [
                'nullable' => true,
                'exclude_sanitize' => true
            ],
            
            'dimensions' => [
                'nullable' => true,
                'exclude_sanitize' => true
            ],

            'availability_type' => [
                'options' => ['in-store','ship to home']
            ],

            'country_of_origin' => [
                'nullable' => true
            ],

            'return_policy' => [
                'nullable' => true
            ],
            'warning' => [
                'nullable' => true
            ],

            'large_image' => [
                'nullable' => true
            ],
            'small_image' => [
                'nullable' => true
            ],

            'currency' => [
                'nullable' => true
            ],

            'weight' => [
                'nullable' => true
            ],


            'serving_size' => [
                'nullable' => true
            ],
            'household_serving_size' => [
                'nullable' => true
            ],


            'brand' => [
                'nullable' => true
            ],

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

            'site_product_id' => [
                'type' => 'int'
            ],

            'company_id' => [
                'type' => 'int'
            ],

            'last_checked' => [
                'ignore' => true,
                'nullable' => true
            ]
        ];

        $this->fields($fields);

    }

}

?>