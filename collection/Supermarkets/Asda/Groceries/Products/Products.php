<?php

namespace Collection\Supermarkets\Asda\Groceries\Products;

use Exception;

use Collection\Supermarkets\Asda\Asda;

use Collection\Services\SharedCategoryService;
use Collection\Services\SharedProductCreateService;
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

    private $shared_product_create_service;

    function __construct(Config $config, Logger $logger, Database $database, Remember $remember=null)
    {
        parent::__construct($config,$logger,$database,$remember);

        $this->product_service = new SharedProductService(new ProductService($config,$logger,$database));

        $this->category_service = new SharedCategoryService($database);

        $this->product_group_service = new ProductGroupService($config, $logger, $database);

        $this->product_detail_service = new ProductDetailService($config, $logger, $database);

        $this->shared_product_create_service = new SharedProductCreateService($database, $this->product_service, $this->product_group_service, $this->category_service);
    }

    public function create_product($site_product_id, $category_details, $request_type = null){
        $parsed_product = $this->product_details($site_product_id, false, $request_type);

        if(!is_null($parsed_product)){
            $product_id = $this->shared_product_create_service->create($site_product_id, $parsed_product, $category_details, $this->store_type_id);
        } else {
            $this->logger->error('Product details not found. Skipping');
            return null;
        }
        
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

        if(!is_null($product_details->price)){
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