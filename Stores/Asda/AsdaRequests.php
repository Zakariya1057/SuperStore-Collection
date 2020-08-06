<?php

namespace Stores\Asda;

use Shared\Requests;
use Model\ReviewModel;
use Models\ProductModel;

class AsdaRequests extends Requests {

    public $logger,$config;
    private $endpoints,$env;

    function __construct($config,$logger){
        $this->logger  = $logger;
        $this->config  = $config;

        $this->endpoints = $this->config->get("endpoints");
        $this->env = $this->config->get("env");
    }

    public function groceries(){

        $groceries_endpoint = $this->endpoints->asda->groceries;

        if($this->env == "dev"){
            $this->logger->debug("Running In Dev Environment. Using File");
            $groceries_response = file_get_contents(__DIR__."/../../Data/Groceries.json");
        } else {
            $this->logger->debug("Running In Prod Environment. Requesting Groceries From Site");
            $groceries_response = $this->request($groceries_endpoint);
        }
        
        return $this->parse_json($groceries_response);

    }

    public function shelf_products($site_shelf_id){
        //Return an array of products with details. To be then be inserted into database
        $products = [];

        $shelf_endpoint = $this->endpoints->asda->shelves . $site_shelf_id;
        $this->logger->debug("Shelf: $site_shelf_id");

        if($this->env == "dev"){
            $this->logger->debug("Running In Dev Environment. Using Products File");
            $shelf_results = file_get_contents(__DIR__."/../../Data/Shelf.json");
        } else {
            $this->logger->debug("Running In Prod Environment. Requesting Shelf Products From Site");
            $shelf_results = $this->request($shelf_endpoint);
        }
        
        $shelf_data = $this->parse_json($shelf_results);

        foreach($shelf_data->contents[0] as $header => $content_parts){

            if(strtolower($header) == "maincontent"){

                foreach($content_parts as $content_part){
                    if(strtolower($content_part->{'@type'}) == "aislecontentholder" ){

                        $records = (object)$content_part->dynamicSlot->contents[0]->mainContent[0]->records;

                        foreach($records as $record){
                            $attributes =  $record->{'attributes'};
                            $product_id = $attributes->{'product.repositoryId'}[0];
                            $name = $attributes->{'sku.displayName'}[0];

                            // $this->logger->debug("Name: $name \tProduct ID: $product_id");
                            // $product = $this->product($product_id);

                            $products[] = $product_id;
                            
                        }

                    }
                }

            }
            
        }
        
        return $products;

    }


    public function product($product_id){

        $product = new ProductModel();

        $shelf_endpoint = $this->endpoints->asda->products . $product_id;
        $this->logger->debug("Product Details ID: $product_id");

        if($this->env == "dev"){
            $this->logger->debug("Running In Dev Environment. Using Products File");
            $shelf_results = file_get_contents(__DIR__."/../../Data/Shelf.json");
        } else {
            $this->logger->debug("Running In Prod Environment. Requesting Shelf Products From Site");
            $shelf_results = $this->request($shelf_endpoint);
        }
        
        $shelf_data = $this->parse_json($shelf_results);

        return $product;
    }

    public function reviews($product_id){

        $reviews_endpoint = $this->endpoints->asda->reviews . $product_id;
        $this->logger->debug("Review Product ID: $product_id");

        if($this->env == "dev"){
            $this->logger->debug("Running In Dev Environment. Using Reviews File");
            $reviews_results = file_get_contents(__DIR__."/../../Data/Reviews.json");
        } else {
            $this->logger->debug("Running In Prod Environment. Requesting Reviews Products From Site");
            $reviews_results = $this->request($reviews_endpoint);
        }
        
        // $reviews = $this->parse_json($reviews_results);

        // return $reviews;
        $product = new ProductModel();
        $product->name = "Name";

        // Later call product->save();
        // This will check if new or insert into database
        
        return $product;

    }

}

?>