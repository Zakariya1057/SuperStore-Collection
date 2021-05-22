<?php

namespace Collection\Supermarkets\Canadian_Superstore\Groceries\Products;

use Collection\Services\SharedProductService;
use Interfaces\ProductInterface;
use Models\Product\ProductModel;

use Collection\Supermarkets\Canadian_Superstore\CanadianSuperstore;

use Collection\Supermarkets\Canadian_Superstore\Services\ProductDetailService;
use Collection\Supermarkets\Canadian_Superstore\Services\ProductService;
use Monolog\Logger;
use Services\ConfigService;
use Services\DatabaseService;
use Services\LoggerService;
use Services\RememberService;

class Products extends CanadianSuperstore implements ProductInterface {
    private $product_v2, $product_v3;

    public $product_service, $product_detail_service;

    function __construct(ConfigService $config_service, Logger $logger, DatabaseService $database_service, RememberService $remember_service=null)
    {
        parent::__construct($config_service, $logger, $database_service, $remember_service);

        $this->shared_product_service = new SharedProductService($database_service);
        $this->product_service = new ProductService($config_service, $logger, $database_service);
    }

    private function setupProductSources(){
        if(is_null($this->product_v2) || is_null($this->product_v3)){
            $this->product_v2 = new ProductV2($this->config_service, $this->logger, $this->database_service, $this->remember_service);
            $this->product_v3 = new ProductV3($this->config_service, $this->logger, $this->database_service, $this->remember_service);
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
        $product_response = $this->product_service->request_product($site_product_id);

        $this->setupProductSources();

        if(!is_null($product_response)){
            if($product_response['type'] == 'v2'){
                $parsed_product = $this->product_v2->parse_product($product_response['response'], $ignore_image);
            } else {
                $parsed_product = $this->product_v3->parse_product($product_response['response'], $ignore_image);
            }
    
            return $parsed_product;
        } else {
            $this->logger->notice('Product Details Not Found: '. $site_product_id);
            return null;
        }

    }
}

?>