<?php

namespace Collection\Services;

use Models\Product\ProductModel;
use Services\DatabaseService;

class SharedProductService {
    private $database_service;

    private $product_model, $request_service;
    private $category_service, $product_group_service;

    public function __construct(DatabaseService $database_service){
        $this->database_service = $database_service;

        $this->product_model = new ProductModel($database_service);

        $this->category_service = new SharedCategoryService($database_service);
        $this->product_group_service = new SharedProductGroupService($database_service);
    }

    public function product_exists($site_product_id, $store_type_id){
        $product_results = $this->product_model->where(['store_type_id' => $store_type_id, 'site_product_id' => $site_product_id])->get()[0] ?? null;

        if(!is_null($product_results)){
            return $product_results->id;
        } else {
            return null;
        }
    }

    // Create Product
    public function create(string $site_product_id, ProductModel $parsed_product, $category_details, int $store_type_id){

        // Start Transaction
        $this->database_service->start_transaction();

        $product_id = $this->product_exists($site_product_id, $store_type_id);

        $product_group_id = $this->product_group_service->create($parsed_product, $category_details->id, $store_type_id);

        if(is_null($product_id)){
            $product_id = $this->create_product($parsed_product);

            $this->create_promotion($parsed_product);

            $this->create_images($product_id, $parsed_product);
            $this->create_ingredients($product_id, $parsed_product);
            $this->create_barcodes($product_id, $parsed_product);

            $this->create_prices($product_id, $parsed_product);

            $this->category_service->create($category_details, $product_id, $product_group_id);
        } else {
            if($this->category_service->category_exists($category_details, $product_id)){
                $this->category_service->update($category_details, $product_id, $product_group_id);
            } else {
                $this->category_service->create($category_details, $product_id, $product_group_id);
            }
        }

        // Commit Transaction
        $this->database_service->commit_transaction();

        return $product_id;
    }

    private function create_product(ProductModel $product){
        return $product->save();
    }

    private function create_barcodes(int $product_id, $product){
        if(key_exists('barcodes', $product)){
            foreach($product->barcodes as $barcode){
                $barcode->product_id = $product_id;
                $barcode->save();
            }
        }
    }

    private function create_ingredients(int $product_id, $product){
        if(count($product->ingredients) > 0){
            foreach($product->ingredients as $ingredient){
                $ingredient->product_id = $product_id;
                $ingredient->insert_ignore = true;
                $ingredient->save();
            }
        }
    }

    public function create_promotion(ProductModel &$product){
        $product->region_promotions = [];

        foreach($product->promotions as $promotion){
            if(property_exists($promotion, 'id')){
                $product->region_promotions[$promotion->region_id] = $promotion->id;
            } else {
                $product->region_promotions[$promotion->region_id] = $promotion->save();
            }
        }
    }

    private function create_images(int $product_id, $product){
        foreach($product->images as $image){
            $image->product_id = $product_id;
            $image->insert_ignore = true;
            $image->save();
        }
    }

    private function create_prices(int $product_id, $product){
        foreach($product->prices as $price){
            if(key_exists($price->region_id, $product->region_promotions)){
                $price->promotion_id = $product->region_promotions[$price->region_id];
            }
            
            $price->product_id = $product_id;
            $price->save();
        }
    }
}

?>