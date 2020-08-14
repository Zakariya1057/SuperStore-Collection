<?php
// Create Model Class. Use For Inserting Into Databas. Searching And Deleting.
namespace Models\Product;

require_once __DIR__.'/../vendor/autoload.php';

use Shared\Database;
use Shared\Loggers;
use Models\Model;

class ProductModel extends Model {

    public $database, $logger,$product;

    public 
        $name,
        $description,
        $large_image,
        $small_image, 
        $price,
        $old_price,
        $weight,
        $brand,
        $dietary_info,
        $allergen_info,
        $ingredients,
        $storage, 
        $promotion_id,
        
        $total_reviews_count,
        $avg_rating,

        $parent_category_id,
        $site_type_id,
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

            'weight' => [
                //Convert from kg to g
                // 'type' => 'gram_weight'
            ],

            'brand' => [],

            'dietary_info' => [
                'nullable' => true
            ],
            'allergen_info' => [
                'nullable' => true
            ],
            'ingredients' => [
                'nullable'=> true
            ],

            'storage' => [
                'nullable'=> true
            ],

            'promotion_id' => [
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
            'site_type_id' => [
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