<?php

namespace Supermarkets\Asda\Groceries\Products;

use Models\Product\ProductModel;
use Models\Product\ReviewModel;
use Exception;
use Monolog\Logger;
use Services\Config;
use Services\Database;
use Services\Remember;
use Supermarkets\Asda\Asda;

class Reviews extends Asda {

    public $product_model,$promotions;

    function __construct(Config $config, Logger $logger, Database $database, Remember $remember=null)
    {
        parent::__construct($config,$logger,$database,$remember);
        $this->promotions = new Promotions($this->config,$this->logger,$this->database,$this->remember);
        $this->product_model = new ProductModel($this->database);
    }

    public function reviews(){
        //Get all products without reviews, get matching reviews
        $this->logger->notice('------ Product Reviews Start ---------');

        $products_without_reviews = $this->product_model->select(['id','site_product_id','name'])
        ->where(['store_type_id' => $this->store_type_id, 'reviews_searched' => null])
        ->order_by('products.id')
        ->get() ?? null;

        if($products_without_reviews){

            $product_count = count($products_without_reviews);

            $this->logger->debug("Found $product_count Products Without Reviews Found");
    
            foreach($products_without_reviews as $product){
                $name = $product->name;
                $product_id = $product->id;
                $site_product_id = $product->site_product_id;
    
                $this->logger->debug("New Product Review Item: [$product_id] $name");
    
                $this->database->start_transaction();
                $this->create_review($product_id, $site_product_id);
                $this->database->commit_transaction();
            }

        } else {
            $this->logger->notice('No Product Without Reviews Found');
        }

        $this->logger->notice('------ Product Reviews Complete ---------');

    }

    public function create_review($product_id,$product_site_id){

        $reviews_endpoint = $this->endpoints->reviews . $product_site_id;

        $this->logger->debug("Reviews Products ID: $product_site_id");

        if($this->env == 'dev'){
            $reviews_response = file_get_contents(__DIR__."/../../data/Asda/Reviews.json");
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

            $this->product_model->where(['id' => $product_id])->update(['reviews_searched' => 1]);
            
        }

    }

    public function process_reviews($product_id, $reviews_data){
        $unique_reviews = $this->unique_reviews($reviews_data);
        $unique_reviews_count = count($unique_reviews);

        $this->logger->debug("Found $unique_reviews_count Unique Reviews");
    
        foreach($unique_reviews as $review_item){
            $review = new ReviewModel($this->database);
            $review->rating = $review_item->Rating;
            $review->text = preg_replace('/\s*\\\\$/','', ucfirst($review_item->ReviewText ?? ''));
            $review->title = preg_replace('/\s*\\\\$/','', ucfirst( $review_item->Title ?? ''));
            $review->user_id = $this->user_id;
            $review->site_review_id = $review_item->Id;

            if($review->text == '' || $review->title == ''){
                $this->logger->warning('Product Without Review Text/Title Found. Skipping: ' . $review->site_review_id);
                continue;
            }

            $created_date = new \DateTime( $review_item->LastModificationTime );
            $review->created_at = $created_date->format('Y-m-d H:i:s');

            $this->logger->debug("Review Details: $review->title \t $review->rating/5 \t $review->created_at");

            $review->product_id = $product_id;
            $review->database = $this->database;
            $review->save();

        }

    }

    private function unique_reviews($reviews){
        // Return all reviews not in database
        $reviews_data = [];
        
        $this->logger->debug('Total Reviews Count: ' . count($reviews));

        $review_model = new ReviewModel($this->database);

        foreach($reviews as $review){
            $site_review_id = $review->Id;
            $reviews_data[$site_review_id] = $review;
        }

        if($reviews_data != []){
            $reviews_results = $review_model->where_in('site_review_id', array_keys($reviews_data))->get();

            foreach($reviews_results as $review){
               unset($reviews_data[$review->site_review_id]);
            }
        }


        return array_values($reviews_data);
    }


}

?>