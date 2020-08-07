<?php

namespace Stores\Asda;

use Shared\Requests;
use Models\ReviewModel;
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
            $groceries_response = file_get_contents(__DIR__."/../../Data/Groceries.json");
        } else {
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
            $shelf_results = file_get_contents(__DIR__."/../../Data/Shelf.json");
        } else {
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
                            $product_id = $attributes->{'sku.repositoryId'}[0];
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

        $shelf_endpoint = $this->endpoints->asda->products;
        $this->logger->debug("Product Details ID: $product_id");

        if($this->env == "dev"){
            $product_response = file_get_contents(__DIR__."/../../Data/Product.json");
        } else {
            $product_response = $this->request($shelf_endpoint,"POST",[
                "item_ids" => [$product_id], 
                "consumer_contract" => "webapp_pdp",
                "store_id" => "4676",
                "request_origin" => "gi"
            ]);
        }
        
        //Get all product details and set them accordingly
        $product_results = $this->parse_json($product_response);
        $product_details = $product_results->data->uber_item->items[0];

        //Design and build product table, ingredients table,
        $product = new ProductModel();
        $product->name = $product_details->item->name;

        $this->logger->debug('Product Name: '.$product->name);

        return $product;
    }

    public function reviews($product_id){

        $reviews_endpoint = $this->endpoints->asda->reviews . $product_id;

        $this->logger->debug("Reviews Products ID: $product_id");

        $reviews = [];

        if($this->env == "dev"){
            $reviews_response = file_get_contents(__DIR__."/../../Data/Reviews.json");
            $reviews_results = $this->parse_json($reviews_response);
            $reviews = array_merge($reviews, $this->process_reviews($reviews_results->Results));
        } else {
            $reviews_response = $this->request($reviews_endpoint);
            $reviews_results = $this->parse_json($reviews_response);

            $total_reviews = $reviews_results->TotalResults;
            $this->logger->notice($total_reviews . ' Reviews Found');

            for($review_index = 0;$review_index < $total_reviews;$review_index++){

                $this->logger->debug("Reviews Page $review_index");

                $reviews_response = $this->request($reviews_endpoint . '&Limit=100&Offset=' . $review_index * 100);
                $reviews_results = $this->parse_json($reviews_response);

                $reviews = array_merge($reviews, $this->process_reviews($reviews_results->Results));

                $this->logger->debug(count($reviews) . "/$total_reviews");
            }
            
        }

        return $reviews;

    }

    public function process_reviews($reviews_data){
        
        $reviews = [];

        foreach($reviews_data as $review_item){
            $review = new ReviewModel();
            $review->rating = $review_item->Rating;
            $review->review_text = $review_item->ReviewText;
            $review->title = $review_item->Title;
            $review->username = $review_item->UserNickname;
            $review->positive_feedback = $review_item->TotalPositiveFeedbackCount;
            $review->negative_feedback = $review_item->TotalNegativeFeedbackCount;

            $created_date = new \DateTime( $review_item->LastModificationTime );
            $review->created_at = $created_date->format('Y-m-d H:i:s');

            $this->logger->debug("Review Details: $review->title \t $review->rating/5 \t $review->created_at");

            $reviews[] = $review;
        }

        return $reviews;
    }

    public function recommended($product_id){

    }
}

?>