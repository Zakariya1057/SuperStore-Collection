<?php

namespace Stores\Asda;

use Models\ProductModel;
use Shared\Request;

class AsdaShelves {

    private $logger,$request,$config,$database;

    function __construct($config,$logger,$database){
        $this->request = new AsdaRequests($config,$logger);
        $this->logger  = $logger;
        $this->config  = $config;
        $this->database = $database;
    }

    public function details($shelf){
        // Get list of all product sold on shelf. Insert new products
        $products = $this->request->shelf_products($shelf->site_category_id);

        $this->logger->notice('Found '.count($products).' Products For Category: '.$shelf->name);

        //Loop through and insert into database
        foreach($products as $product_item){
            $product = new AsdaProducts($this->config,$this->logger,$this->database);
            $product->product($product_item);
        }
    }

}

?>