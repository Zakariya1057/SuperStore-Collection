<?php

namespace Collection\Services;

use Models\Product\ProductPriceModel;
use Services\DatabaseService;

class SharedProductPriceService {
    private $product_price;

    public function __construct(DatabaseService $database_service){
        $this->product_price = new ProductPriceModel($database_service);
    }

    public function create(ProductPriceModel $product_price){
        $product_price->insert_ignore = true;
        $product_price->save();
    }

    public function create_prices(int $product_id, $product, $insert_ignore = null){
        foreach($product->prices as $price){
            if(key_exists($price->region_id, $product->region_promotions)){
                $price->promotion_id = $product->region_promotions[$price->region_id];
            }
            
            $price->insert_ignore = $insert_ignore;
            $price->product_id = $product_id;
            $price->save();
        }
    }

    public function group_prices($new_prices, $old_prices){
        $all_prices = [];

        foreach($new_prices as $new_price){
            $region_id = $new_price->region_id;

            $prices = ['new_price' => $new_price];

            foreach($old_prices as $old_price){
                if($old_price->region_id == $region_id){
                    $prices['old_price'] = $old_price;
                    break;
                }
            }

            $all_prices[$region_id] = (object)$prices;
        }

        return $all_prices;
    }

    public function order_prices_by_region($prices){
        usort($prices, [ get_class($this), 'order_by_id' ]);
        return $prices;
    }

    public function order_by_id($a, $b){
        return strcmp($a->region_id, $b->region_id);
    }
}

?>