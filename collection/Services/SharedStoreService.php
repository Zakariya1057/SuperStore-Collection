<?php

namespace Collection\Services;

use Interfaces\ProductRequestInterface;
use Models\Product\ProductModel;
use Services\Database;

class SharedStoreService {
    
    private $product_model, $request_service;

    public function __construct(Database $database, ProductRequestInterface $request_service = null){
        $this->product_model = new ProductModel($database);
        $this->request_service = $request_service;
    }

    public function request_product($site_product_id, $request_type = null){
        return $this->request_service->request_product($site_product_id, $request_type = null);
    }

    public function product_exists($site_product_id, $store_type_id){
        $product_results = $this->product_model->where(['store_type_id' => $store_type_id, 'site_product_id' => $site_product_id])->get()[0] ?? null;

        if(!is_null($product_results)){
            return $product_results->id;
        } else {
            return null;
        }
    }

    public function create_product($product){
        return $product->save();
    }

    public function create_barcodes($product_id, $product){
        if(key_exists('barcodes', $product)){
            foreach($product->barcodes as $barcode){
                $barcode->product_id = $product_id;
                $barcode->save();
            }
        }
    }

    public function create_ingredients($product_id, $product){
        if(count($product->ingredients) > 0){
            foreach($product->ingredients as $ingredient){
                $ingredient->product_id = $product_id;
                $ingredient->insert_ignore = true;
                $ingredient->save();
            }
        }
    }

    public function create_promotion(ProductModel &$product){
        if(property_exists($product, 'promotion') && !is_null($product->promotion)){
            if(property_exists($product->promotion, 'id')){
                $product->promotion_id = $product->promotion->id;
            } else {
                $product->promotion_id = $product->promotion->save();
            }
        }
    }

    public function create_images($product_id, $product){
        foreach($product->images as $image){
            $image->product_id = $product_id;
            $image->insert_ignore = true;
            $image->save();
        }
    }
}

?>