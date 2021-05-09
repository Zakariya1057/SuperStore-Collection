<?php

namespace Collection\Services;

use Services\Database;

class SharedProductCreateService
{
    private $product_service, $product_group_service, $category_service;

    private $database;

    function __construct(Database $database, $product_service, $product_group_service, $category_service){
        $this->database = $database;
        
        $this->product_service = $product_service;
        $this->product_group_service = $product_group_service;
        $this->category_service = $category_service;
    }

    public function create($site_product_id, $parsed_product, $category_details, $store_type_id){

        $this->database->start_transaction();

        $product_id = $this->product_service->product_exists($site_product_id, $store_type_id);

        $product_group_id = $this->product_group_service->create($parsed_product, $category_details->id);

        if(is_null($product_id)){
            $product_id = $this->product_service->create_product($parsed_product);

            $this->product_service->create_promotion($parsed_product);

            $this->product_service->create_images($product_id, $parsed_product);
            $this->product_service->create_ingredients($product_id, $parsed_product);
            $this->product_service->create_barcodes($product_id, $parsed_product);

            $this->category_service->create($category_details, $product_id, $product_group_id);
        } else {
            if($this->category_service->category_exists($category_details, $product_id)){
                $this->category_service->update($category_details, $product_id, $product_group_id);
            } else {
                $this->category_service->create($category_details, $product_id, $product_group_id);
            }
        }

        $this->database->commit_transaction();

        return $product_id;
    }
}

?>