<?php

namespace Collection\Loblaws\Groceries\Products;

use Collection\Services\SharedProductService;
use Collection\Services\SharedRegionService;
use Interfaces\ProductInterface;
use Models\Product\ProductModel;

use Collection\Loblaws\Loblaws;

use Collection\Loblaws\Services\ProductService;
use Collection\Services\SharedCategoryService;
use Collection\Services\SharedProductGroupService;
use Monolog\Logger;
use Services\ConfigService;
use Services\DatabaseService;

class Products extends Loblaws implements ProductInterface {
    private $product_v2, $product_v3;

    public $product_service, $product_group_service, $product_detail_service, $category_service;

    function __construct(ConfigService $config_service, Logger $logger, DatabaseService $database_service, SharedRegionService $region_service)
    {
        parent::__construct($config_service, $logger, $database_service);

        $this->product_group_service = new SharedProductGroupService($database_service);
        $this->shared_product_service = new SharedProductService($database_service);
        $this->product_service = new ProductService($config_service, $logger, $database_service);
        $this->category_service = new SharedCategoryService($database_service);

        $this->region_service = $region_service;
    }

    private function setupProductSources(){
        if(is_null($this->product_v2) || is_null($this->product_v3)){
            $this->product_v2 = new ProductV2($this->config_service, $this->logger, $this->database_service, $this->region_service);
            $this->product_v3 = new ProductV3($this->config_service, $this->logger, $this->database_service, $this->region_service);
        }
    }

    public function create_product($site_product_id, $category_details){
        // Check if product exists, if it does then just add to category.
    
        $product_id = $this->shared_product_service->product_exists($site_product_id, $this->company_id);

        $parsed_product = $this->product_details($site_product_id, false);

        if(is_null($parsed_product)){
            $this->logger->error('Product details not found. Skipping');
            return null;
        }

        $product_group_id = $this->product_group_service->create($parsed_product, $category_details->id, $this->company_id);

        if(is_null($product_id)){
            // New Product Insert It
             if( strlen($parsed_product->name) > 255 ){
                $this->logger->error('Product name too long. Length: '. $parsed_product->name);
                return null;
            }
            
            $product_id = $this->shared_product_service->create($parsed_product);

            $this->category_service->create($category_details, $product_id, $product_group_id);

        } else {
            // Insert Ignore Category Product
            if($this->category_service->category_exists($category_details, $product_id)){
                $this->category_service->update($category_details, $product_id, $product_group_id);
            } else {
                $this->category_service->create($category_details, $product_id, $product_group_id);
            }
        }

        return $product_id;
    }

    public function product_details($site_product_id, $ignore_image=false): ?ProductModel {

        $this->setupProductSources();

        $parsed_product = null;
        
        foreach($this->supermarket_chains as $supermarket_chain){
            $regions = $supermarket_chain->regions;

            $supermarket_chain_id = $supermarket_chain->id;
            $supermarket_name = $supermarket_chain->name;
            $supermarket_banner = $supermarket_chain->banner;

            $this->logger->debug("----- $supermarket_name Product Details");

            foreach($regions as $region_name => $region_details){

                $region_id = $region_details->id;
                $site_store_id = $region_details->site_store_id;
    
                $this->logger->debug("--- $region_name Region Product Details");

                $this->logger->debug("Getting Product Details For {$region_name}[{$site_store_id}]");
    
                $product_response = $this->product_service->request_product($site_product_id, $site_store_id, $supermarket_banner);
    
                if(!is_null($product_response)){
                    
                    if(is_null($parsed_product)){
                        if($product_response['type'] == 'v2'){
                            $parsed_product = $this->product_v2->parse_product($product_response['response'], $ignore_image);
                        } else {
                            $parsed_product = $this->product_v3->parse_product($product_response['response'], $ignore_image);
                        }
                    }
    
                    if(is_null($parsed_product)){
                        $this->logger->notice('Product Details Not Found: '. $site_product_id);
                    } else {
    
                        if($product_response['type'] == 'v2'){
                            $product_price = $this->product_v2->parse_prices($product_response['response'], $region_id);
                        } else {
                            $product_price = $this->product_v3->parse_prices($product_response['response'], $parsed_product, $region_id, $supermarket_chain_id);
                        }
        
                        $product_price->supermarket_chain_id = $supermarket_chain->id;
                        
                        if(!is_null($product_price->promotion)){
                            $parsed_product->promotions[] = $product_price->promotion;
                        }
        
                        $parsed_product->prices[] = $product_price;
    
                    }
                } else {
                    $this->logger->notice('Product Details Not Found: '. $site_product_id);
                }
                
            }

        }

        return $parsed_product;

    }
}

?>