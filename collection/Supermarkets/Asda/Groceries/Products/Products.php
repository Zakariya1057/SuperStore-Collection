<?php

namespace Collection\Supermarkets\Asda\Groceries\Products;

use Exception;

use Collection\Supermarkets\Asda\Asda;

use Collection\Services\CategoryService;
use Collection\Services\SharedProductService;
use Collection\Supermarkets\Asda\Services\ProductDetailService;
use Collection\Supermarkets\Asda\Services\ProductGroupService;
use Collection\Supermarkets\Asda\Services\ProductService;

use Models\Product\ProductModel;

use Monolog\Logger;

use Services\Config;
use Services\Database;
use Services\Remember;

use Interfaces\ProductInterface;

class Products extends Asda implements ProductInterface {

    public $product_service, $category_service, $product_group_service;

    public $product_detail_service;

    function __construct(Config $config, Logger $logger, Database $database, Remember $remember=null)
    {
        parent::__construct($config,$logger,$database,$remember);

        $this->product_service = new SharedProductService(new ProductService($config,$logger,$database));

        $this->category_service = new CategoryService($config, $logger, $database);
        $this->product_group_service = new ProductGroupService($config, $logger, $database);

        $this->product_detail_service = new ProductDetailService($config, $logger, $database);
    }

    public function create_product($site_product_id, $category_details, $request_type = null){
        
        $this->database->start_transaction();

        // 1. Request Product Details
        // 2. Parse Response Into Model
        // 3, Create Product Model
        // 4. Save Product
        // 5. Create/Update Category Product

        $parsed_product = $this->product_details($site_product_id, false, $request_type);

        $product_id = $this->product_service->product_exists($site_product_id, $this->store_type_id);

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

    public function product_details($site_product_id, $ignore_image=false,$ignore_promotion=false): ?ProductModel {
        $product_response = $this->product_service->request_product($site_product_id);
        return $this->parse_product($product_response);
    }

    public function parse_product($product_response, $ignore_image = false, $ignore_promotion = false){
        $product_details = $product_response->data->uber_item->items[0];

        $item = $product_details->item;
        $name = $item->name;
        $item_enrichment = $product_details->item_enrichment->enrichment_info;
        $inventory = $product_details->inventory;

        $is_bundle_product = $product_details->is_bundle ?? false;
        if($is_bundle_product){
            return $this->logger->debug('Bundle Product Found');
            return null;
        }

        $this->logger->notice('----- Start Product('.$item->sku_id.'): '.$item->name .' -----');

        $product = new ProductModel($this->database);
        
        $product->name = $this->product_detail_service->clean_product_name($name);
        $product->available = 1;

        $product->images = [];

        if(is_null($product_details->price)){
            $this->logger->notice('Product Not Available. No Price Details Found.');
            $product->available = 0;
            return null;
        }

        $this->product_detail_service->set_product_description($product, $item, $item_enrichment);

        $this->product_detail_service->set_product_details($product, $item_enrichment, $item, $inventory, $ignore_image);

        $this->product_detail_service->set_product_prices($product, $product_details, $ignore_promotion);

        $this->product_detail_service->set_product_group($product, $product_details);

        $this->product_detail_service->set_ingredients($product, $product_details);

        $this->logger->notice('----- Complete Product('.$item->sku_id.'): '.$item->name .' -----');

        return $product;
    }
}

?>