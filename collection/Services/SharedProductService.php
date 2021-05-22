<?php

namespace Collection\Services;

use Interfaces\ProductRequestInterface;
use Models\Product\ProductModel;
use Services\Database;

class SharedProductService {
    private $database;

    private $product_model, $request_service;
    private $category_service, $product_group_service;

    public function __construct(Database $database, ProductRequestInterface $request_service = null){
        $this->database = $database;

        $this->product_model = new ProductModel($database);
        $this->request_service = $request_service;

        $this->category_service = new SharedCategoryService($database);
        $this->product_group_service = new SharedProductGroupService($database);
    }

    public function request_product($site_product_id, $request_type = null){
        return $this->request_service->request_product($site_product_id, $request_type);
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
        $this->database->start_transaction();

        $product_id = $this->product_exists($site_product_id, $store_type_id);

        $product_group_id = $this->product_group_service->create($parsed_product, $category_details->id, $store_type_id);

        if(is_null($product_id)){
            $product_id = $this->create_product($parsed_product);

            $this->create_promotion($parsed_product);

            $this->create_images($product_id, $parsed_product);
            $this->create_ingredients($product_id, $parsed_product);
            $this->create_barcodes($product_id, $parsed_product);

            $this->category_service->create($category_details, $product_id, $product_group_id);
        } else {
            if($this->category_service->category_exists($category_details, $product_id)){
                $this->category_service->update($category_details, $product_id, $product_group_id);
            } else {
                $this->category_service->create($category_details, $product_id, $product_group_id);
            }
        }

        // Commit Transaction
        $this->database->commit_transaction();

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
        if(property_exists($product, 'promotion') && !is_null($product->promotion)){
            if(property_exists($product->promotion, 'id')){
                $product->promotion_id = $product->promotion->id;
            } else {
                $product->promotion_id = $product->promotion->save();
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
}

?>