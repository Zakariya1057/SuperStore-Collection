<?php

namespace Stores\Asda;

use Shared\Request;
use Stores\Asda\AsdaRequests;

class AsdaProducts {

    private $logger,$request,$config,$database;

    function __construct($config,$logger,$database){
        $this->request = new AsdaRequests($config,$logger);
        $this->logger  = $logger;
        $this->config  = $config;
        $this->database = $database;
    }

    public function product($product_id){
        //Get product details for each product and insert into database.

        //Check if product exists in database. If does then skip.
        //Otherwise get details and insert into database
        $this->logger->info("Product ID: $product_id");

        $product_request = new AsdaRequests($this->config,$this->logger);

        $product_details = $product_request->product($product_id);
        $product_reviews = $product_request->reviews($product_id);
        
        $related_products = $product_request->recommended($product_id);
    }

}

?>