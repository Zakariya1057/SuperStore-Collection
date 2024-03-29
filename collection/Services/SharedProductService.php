<?php

namespace Collection\Services;

use Models\Product\ProductModel;
use Models\Product\PromotionModel;
use Services\DatabaseService;

class SharedProductService {
    private $database_service;

    private $product_model;
    private $category_service, $product_group_service;

    private $product_price_service;
    private $nutrition_service;

    public function __construct(DatabaseService $database_service){
        $this->database_service = $database_service;

        $this->product_model = new ProductModel($database_service);

        $this->category_service = new SharedCategoryService($database_service);
        $this->product_group_service = new SharedProductGroupService($database_service);

        $this->product_price_service = new SharedProductPriceService($database_service);
        $this->nutrition_service = new SharedNutritionService($database_service);
    }

    public function product_exists($site_product_id, $company_id){
        $product_results = $this->product_model->where(['company_id' => $company_id, 'site_product_id' => $site_product_id])->first();

        if(!is_null($product_results)){
            return $product_results->id;
        } else {
            return null;
        }
    }

    // Create Product
    public function create(ProductModel $parsed_product){

        // Start Transaction
        $this->database_service->start_transaction();

        $product_id = $this->create_product($parsed_product);

        $this->create_product_promotions($parsed_product);

        $this->create_images($product_id, $parsed_product);
        $this->create_ingredients($product_id, $parsed_product);
        $this->create_barcodes($product_id, $parsed_product);

        $this->nutrition_service->create_nutritions($product_id, $parsed_product);

        $this->product_price_service->create_prices($product_id, $parsed_product);

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

    public function create_product_promotions(ProductModel &$product){
        $product->region_promotions = [];

        foreach($product->promotions as $promotion){
            $product->region_promotions[$promotion->region_id] = $this->create_promotion($promotion);
        }
    }

    public function create_promotion(PromotionModel &$promotion){
        if(property_exists($promotion, 'id')){
            return $promotion->id;
        } else {
           return $promotion->save();
        }
    }

    private function create_images(int $product_id, $product){
        foreach($product->images as $image){
            $image->product_id = $product_id;
            $image->insert_ignore = true;
            $image->save();
        }
    }

}

?>