<?php

namespace Collection\Loblaws\Services;

use Collection\Loblaws\Loblaws;
use Models\Flyer\FlyerModel;
use Models\Flyer\FlyerProductModel;
use Models\Product\ProductModel;

class FlyerProductService extends Loblaws {

    private $product_model, $flyer_product_model;

    private function setupClasses(){
        if(is_null($this->product_model)){
            $this->product_model = new ProductModel($this->database_service);
            $this->flyer_product_model = new FlyerProductModel($this->database_service);
        }
    }

    public function set_flyer_products(FlyerModel &$flyer, $flyer_data){
        $this->setupClasses();

        $items = $flyer_data->items;
        
        $item_ids = [];
        $flyer_products = [];

        foreach($items as $item){
            $site_store_id = $item->sku;
            if($site_store_id != ''){
                $item_ids[] = $site_store_id;
            }
        }

        $products = $this->product_model->select('id')->regex('site_product_id', $item_ids, '.+|')->get();

        foreach($products as $product){
            $flyer_product = clone $this->flyer_product_model;
            $flyer_product->product_id = $product->id;

            $flyer_products[] = $flyer_product;
        }
        
        $flyer->products = $flyer_products;

    }

}

?>