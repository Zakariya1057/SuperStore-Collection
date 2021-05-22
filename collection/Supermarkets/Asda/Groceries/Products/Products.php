<?php

namespace Collection\Supermarkets\Asda\Groceries\Products;

use Exception;

use Collection\Supermarkets\Asda\Asda;

use Collection\Services\SharedProductService;
use Collection\Supermarkets\Asda\Services\ProductDetailService;
use Collection\Supermarkets\Asda\Services\ProductService;

use Models\Product\ProductModel;

use Monolog\Logger;

use Services\Config;
use Services\Database;
use Services\Remember;

use Interfaces\ProductInterface;

class Products extends Asda implements ProductInterface {

    private $product_service;
    private $shared_product_service;

    function __construct(Config $config, Logger $logger, Database $database, Remember $remember=null)
    {
        parent::__construct($config,$logger,$database,$remember);
        $this->shared_product_service = new SharedProductService($database, new ProductService($config,$logger,$database));
        $this->product_service = new ProductService($config, $logger, $database);
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

    public function product_details($site_product_id, $ignore_image=false,$ignore_promotion=false): ?ProductModel {
        $product_response = $this->product_service->request_product($site_product_id);
        return $this->product_service->parse_product($product_response, $ignore_image, $ignore_promotion);
    }
}

?>