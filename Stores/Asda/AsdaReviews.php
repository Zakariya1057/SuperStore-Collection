<?php

namespace Stores\Asda;

use Models\Category\ChildCategoryModel;
use Models\Product\ProductModel;
use Models\Product\ReviewModel;
use Exception;

class AsdaReviews extends Asda {

    public $productModel,$promotions;

    function __construct($config,$logger,$database,$remember)
    {
        parent::__construct($config,$logger,$database,$remember);
        $this->promotions = new AsdaPromotions($this->config,$this->logger,$this->database,$this->remember);
        $this->productModel = new ProductModel($this->database);
    }

    public function reviews(){
        //Get all products without reviews, get matching reviews
        $this->logger->notice('------ Product Reviews Start ---------');

        $products_without_reviews = $this->productModel->select(['id','site_product_id','name'])->where(['reviews_searched' => null])->get();

        if(is_object($products_without_reviews) ){
            $products_without_reviews = [$products_without_reviews];
        }

        if($products_without_reviews){

            $product_count = count($products_without_reviews);

            $this->logger->debug("Found $product_count Products Without Reviews Found");
    
            foreach($products_without_reviews as $product){
                $name = $product->name;
                $product_id = $product->id;
                $site_product_id = $product->site_product_id;
    
                $this->logger->debug("New Product Review Item: [$product_id]$name");
    
                $this->database->start_transaction();
                $this->insert_review($product_id, $site_product_id);
                $this->database->end_transaction();
            }

        } else {
            $this->logger->notice('No Product Without Reviews Found');
        }

        $this->logger->notice('------ Product Reviews Complete ---------');

    }

    public function insert_review($product_id,$product_site_id){

        $reviews_endpoint = $this->endpoints->reviews . $product_site_id;

        $this->logger->debug("Reviews Products ID: $product_site_id");

        if($this->env == "dev"){
            $reviews_response = file_get_contents(__DIR__."/../../Data/Asda/Reviews.json");
            $reviews_results = $this->request->parse_json($reviews_response);
            $this->process_reviews($product_id, $reviews_results->Results);
        } else {
            $reviews_response = $this->request->request($reviews_endpoint);
            $reviews_results = $this->request->parse_json($reviews_response);

            $total_reviews = $reviews_results->TotalResults;
            $this->logger->notice($total_reviews . ' Reviews Found');

            if($total_reviews > 100){
                $total_pages = ceil($total_reviews / 100 );
            } else {
                $total_pages = 1;
            }

            $this->logger->notice("Total Review Pages: $total_pages");

            for($review_page = 0;$review_page < $total_pages;$review_page++){

                $this->logger->debug("Reviews Page $review_page");

                $reviews_response = $this->request->request($reviews_endpoint . '&Limit=100&Offset=' . $review_page * 100);
                $reviews_results = $this->request->parse_json($reviews_response);

                $this->process_reviews($product_id, $reviews_results->Results);
            }

            $this->logger->debug('Updating Product Review_Searched');

            $this->productModel->where(['id' => $product_id])->update(['reviews_searched' => 1]);
            
        }

    }

    public function process_reviews($product_id, $reviews_data){

        foreach($reviews_data as $review_item){
            $review = new ReviewModel($this->database);
            $review->rating = $review_item->Rating;
            $review->text = $review_item->ReviewText;
            $review->title = $review_item->Title ?? '';
            $review->user_id = $this->user_id;
            $review->site_review_id = $review_item->Id;

            $created_date = new \DateTime( $review_item->LastModificationTime );
            $review->created_at = $created_date->format('Y-m-d H:i:s');

            $this->logger->debug("Review Details: $review->title \t $review->rating/5 \t $review->created_at");

            $select_review = $review->where(['site_review_id' => $review->site_review_id])->get();

            if(is_null($select_review)){
                $review->product_id = $product_id;
                $review->database = $this->database;
                $review->save();
            } else {
                $this->logger->error('Ignoring Duplicate Review In Database: ' . $select_review->id);
            }

        }

    }


}

?>