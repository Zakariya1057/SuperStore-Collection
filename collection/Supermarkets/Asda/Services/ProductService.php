<?php

namespace Collection\Supermarkets\Asda\Services;

use Exception;
use Collection\Supermarkets\Asda\Asda;
use Interfaces\ProductRequestInterface;
use Models\Product\ProductModel;

class ProductService extends Asda implements ProductRequestInterface {

    public function request_product($site_product_id){

        $shelf_endpoint = $this->endpoints->products;
        $this->logger->debug("Product Details ID: $site_product_id");

        if($this->env == 'dev'){
            $product_response = file_get_contents( __DIR__. '/../../' . $this->config->get('test_files.product'));
        } else {

            $product_response = $this->request->request($shelf_endpoint,'POST',[
                'item_ids' => [$site_product_id], 
                'consumer_contract' => 'webapp_pdp',
                'store_id' => '4565', // Change for different regions. Different stores, different prices
                'request_origin' => 'gi'
            ]);

        }
        
        if($product_response){
            $this->logger->debug('Product Returned');
        } else {
            $this->logger->debug('No Product Returned');
            return null;
        }

        return $this->request->parse_json($product_response);

    }
}

?>