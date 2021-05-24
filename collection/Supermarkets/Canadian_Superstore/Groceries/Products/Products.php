<?php

namespace Collection\Supermarkets\Canadian_Superstore\Groceries\Products;

use Collection\Services\SharedProductService;
use Collection\Services\SharedRegionService;
use Interfaces\ProductInterface;
use Models\Product\ProductModel;

use Collection\Supermarkets\Canadian_Superstore\CanadianSuperstore;

use Collection\Supermarkets\Canadian_Superstore\Services\ProductService;
use Monolog\Logger;
use Services\ConfigService;
use Services\DatabaseService;

class Products extends CanadianSuperstore implements ProductInterface {
    private $product_v2, $product_v3;

    public $product_service, $product_detail_service;

    public $region_service, $store_regions;

    function __construct(ConfigService $config_service, Logger $logger, DatabaseService $database_service, SharedRegionService $region_service)
    {
        parent::__construct($config_service, $logger, $database_service);

        $this->shared_product_service = new SharedProductService($database_service);
        $this->product_service = new ProductService($config_service, $logger, $database_service);

        $this->region_service = $region_service;
        $this->store_regions = $region_service->get_regions($this->store_type_id);
    }

    private function setupProductSources(){
        if(is_null($this->product_v2) || is_null($this->product_v3)){
            $this->product_v2 = new ProductV2($this->config_service, $this->logger, $this->database_service, $this->region_service);
            $this->product_v3 = new ProductV3($this->config_service, $this->logger, $this->database_service, $this->region_service);
        }
    }

    public function create_product($site_product_id, $category_details, $request_type = null){
        $parsed_product = $this->product_details($site_product_id, false, $request_type);

        if(!is_null($parsed_product)){
            $product_id = $this->shared_product_service->create($site_product_id, $parsed_product, $category_details, $this->store_type_id);
        } else {
            $this->logger->error('Product details not found. Skipping');
            return null;
        }

        return $product_id;
    }

    public function product_details($site_product_id, $ignore_image=false): ?ProductModel {

        $this->setupProductSources();

        $parsed_product = null;
        
        foreach($this->store_regions as $region){
            $region_id = $region->id;
            $region_name = $region->name;
            $store_id = $region->store_id;

            $this->logger->debug("Getting Product Details For {$region_name}[{$store_id}]");

            $product_response = $this->product_service->request_product($site_product_id, $store_id);

            if(!is_null($product_response)){
                
                if(is_null($parsed_product)){
                    if($product_response['type'] == 'v2'){
                        $parsed_product = $this->product_v2->parse_product($product_response['response'], $ignore_image);
                    } else {
                        $parsed_product = $this->product_v3->parse_product($product_response['response'], $ignore_image);
                    }
                }

                if($product_response['type'] == 'v2'){
                    $product_price = $this->product_v2->parse_prices($product_response['response'], $region_id);
                } else {
                    $product_price = $this->product_v3->parse_prices($product_response['response'], $parsed_product, $region_id);
                }

                if(!is_null($product_price->promotion)){
                    $parsed_product->promotions[] = $product_price->promotion;
                }

                $parsed_product->prices[] = $product_price;
            } else {
                $this->logger->notice('Product Details Not Found: '. $site_product_id);
            }

        }

        return $parsed_product;

    }
}

?>