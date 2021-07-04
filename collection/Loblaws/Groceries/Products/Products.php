<?php

namespace Collection\Loblaws\Groceries\Products;

use Collection\Services\SharedProductService;
use Collection\Services\SharedRegionService;
use Interfaces\ProductInterface;
use Models\Product\ProductModel;

use Collection\Loblaws\Loblaws;

use Collection\Loblaws\Services\ProductService;
use Monolog\Logger;
use Services\ConfigService;
use Services\DatabaseService;

class Products extends Loblaws implements ProductInterface {
    private $product_v2, $product_v3;

    public $product_service, $product_detail_service;

    public $store_regions;

    function __construct(ConfigService $config_service, Logger $logger, DatabaseService $database_service, SharedRegionService $region_service)
    {
        parent::__construct($config_service, $logger, $database_service);

        $this->shared_product_service = new SharedProductService($database_service);
        $this->product_service = new ProductService($config_service, $logger, $database_service);

        $this->region_service = $region_service;
        // $this->store_regions = $region_service->get_regions($this->company_id);
    }

    private function setupProductSources(){
        if(is_null($this->product_v2) || is_null($this->product_v3)){
            $this->product_v2 = new ProductV2($this->config_service, $this->logger, $this->database_service, $this->region_service);
            $this->product_v3 = new ProductV3($this->config_service, $this->logger, $this->database_service, $this->region_service);
        }
    }

    public function create_product($site_product_id, $category_details){
        $parsed_product = $this->product_details($site_product_id, false);

        if(!is_null($parsed_product)){
            $product_id = $this->shared_product_service->create($site_product_id, $parsed_product, $category_details, $this->company_id);
        } else {
            $this->logger->error('Product details not found. Skipping');
            return null;
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
    
                $this->logger->debug("--- $region_name Regioon Product Details");

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