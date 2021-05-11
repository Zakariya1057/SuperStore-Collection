<?php

namespace Collection\Supermarkets\Canadian_Superstore\Groceries\Products;

use Collection\Services\SharedCategoryService;
use Collection\Services\SharedProductCreateService;
use Collection\Services\SharedProductService;
use Interfaces\ProductInterface;
use Models\Product\ProductModel;

use Collection\Supermarkets\Canadian_Superstore\CanadianSuperstore;

use Monolog\Logger;
use Services\Config;
use Services\Database;
use Services\Remember;

use Collection\Supermarkets\Canadian_Superstore\Services\ProductDetailService;
use Collection\Supermarkets\Canadian_Superstore\Services\ProductGroupService;
use Collection\Supermarkets\Canadian_Superstore\Services\ProductService;

class Products extends CanadianSuperstore implements ProductInterface {
    private $product_v2, $product_v3;

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

    private function setupProductSources(){
        if(is_null($this->product_v2) || is_null($this->product_v3)){
            $this->product_v2 = new ProductV2($this->config, $this->logger, $this->database, $this->remember);
            $this->product_v3 = new ProductV3($this->config, $this->logger, $this->database, $this->remember);
        }
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