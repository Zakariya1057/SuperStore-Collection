<?php
 
namespace Models\Product;

use Models\Model;
use Services\DatabaseService;

class ProductPriceModel extends Model {

    public 
        $price,
        $old_price, 

        $is_on_sale,
        $sale_ends_at,
        $promotion_id,

        $out_of_stock,

        $supermarket_chain_id,
        $region_id,

        $product_id;

    function __construct(DatabaseService $database_service=null){

        parent::__construct($database_service);

        $this->table('product_prices');

        $fields = [
            'price' => [
                'type' => 'price'
            ],
            'old_price' => [
                'type' => 'price',
                'nullable' => true
            ],

            'sale_ends_at' => [
                'nullable' => true
            ],

            'is_on_sale' => [
                'nullable' => true
            ],

            'supermarket_chain_id' => [],
            'region_id' => [],

            'product_id' => [
                'nullable' => true
            ],
            
            'promotion_id' => [
                'nullable' => true
            ]
        ];

        $this->fields($fields);

    }

}

?>