<?php

namespace Stores\Asda;

use Models\Category\ChildCategoryModel;
use Models\Product\ProductModel;
use Models\Product\ReviewModel;
use Models\Product\IngredientModel;
use Exception;

class AsdaProducts extends Asda {

    public $product_details,$promotions;

    function __construct($config,$logger,$database)
    {
        parent::__construct($config,$logger,$database);
        $this->promotions = new AsdaPromotions($this->config,$this->logger,$this->database);
    }

    public function product($product_site_id,$parent_category_id=null,$parent_site_category_name=null){
        //Get product details for each product and insert into database.

        $this->logger->info("Product ID: $product_site_id");

        $product_item = new ProductModel($this->database);
        $product_results = $product_item->where(['site_product_id' => $product_site_id])->get();

        if(is_null($product_results)){
            
            $this->logger->info("New Product Found: $product_site_id");

            $product_details  = $this->product_details($product_site_id);

            if(!$parent_category_id){

                if(!$parent_site_category_name){
                    throw new Exception('Parent Category Id or Parent Site Category Id Required');
                }

                $category = new ChildCategoryModel($this->database);
                $category_details = $category->like(['name'=> "$parent_site_category_name%"])->get();

                if(!$category_details){
                    throw new Exception('Failed To Find Matching Parent Category');
                } else {
                    $parent_category_id =  $category_details->id;
                }
                
            }

            //Product
            $product_details->parent_category_id = $parent_category_id;
            $product_details->database = $this->database;
            $product_id = $product_details->save();

            $this->reviews($product_id,$product_site_id);
            $this->ingredients($product_id,$this->product_details);


            return $product_id;

        } else { 
            $this->logger->info("Product Found In Database: $product_site_id");
        }

    }

    public function product_details($product_id){

        $shelf_endpoint = $this->endpoints->products;
        $this->logger->debug("Product Details ID: $product_id");

        if($this->env == "dev"){
            $product_response = file_get_contents(__DIR__."/../../Data/Asda/Product.json");
        } else {
            $product_response = $this->request->request($shelf_endpoint,"POST",[
                "item_ids" => [$product_id], 
                "consumer_contract" => "webapp_pdp",
                "store_id" => "4676",
                "request_origin" => "gi"
            ]);
        }
        
        //Get all product details and set them accordingly
        $product_results = $this->request->parse_json($product_response);
        $product_details = $product_results->data->uber_item->items[0];

        //Design and build product table, ingredients table,

        $item = $product_details->item;
        $item_enrichment = $product_details->item_enrichment->enrichment_info;
        $rating_review = $item->rating_review;

        $this->logger->notice('--- Start Product('.$item->sku_id.'): '.$item->name .' ---');

        $product = new ProductModel();
        $product->name = $item->name;
        $product->site_type_id = $this->site_type_id;
        $product->description = $item->description == '.' ? NULL : $item->description;
        $product->site_product_id = $item->sku_id;

        $product->total_reviews_count = $rating_review->total_review_count;
        $product->avg_rating          = $rating_review->avg_star_rating;

        $product->url = "https://groceries.asda.com/product/{$item->sku_id}";

        $image_id = $item->images->scene7_id;
        
        $product->large_image = $this->product_image($image_id,300);
        $product->small_image = $this->product_image($image_id,150);

        $product->brand = $item->brand;
        $product->dietary_info = $item_enrichment->dietary_info ?? NULL;
        $product->allergen_info = $item_enrichment->allergy_info_formatted_web ?? NULL;

        $product->storage = $item_enrichment->storage ?? NULL;

        if($item->extended_item_info->weight){
            $product->weight = $this->weight_converter->grams($item->extended_item_info->weight);
        }

        // Promotion Types:
        // * 2 for £10. Product Grouped
        // * Rollback
        // * Sale.

        //This will get product price, regardless of promotions or not
        $product_prices = $this->promotions->product_prices($product_details);

        $product->price = $product_prices->price;
        $product->old_price = $product_prices->old_price;
        $product->is_on_sale = $product_prices->is_on_sale;
        $product->promotion_id = $product_prices->promotion_id;

        $this->product_details = $product_details;

        $this->logger->notice('--- Complete Product('.$item->sku_id.'): '.$item->name .' ---');

        return $product;
    }

    public function product_image($image_id,$size){
        // return "https://ui.assets-asda.com/dm/asdagroceries/{$product_upc}_T1?defaultImage=asdagroceries/noImage&resMode=sharp2&id=WLURx1&fmt=jpg&fit=constrain,1&wid=$size&hei=$size";
        return "https://ui.assets-asda.com/dm/asdagroceries/{$image_id}?defaultImage=asdagroceries/noImage&resMode=sharp2&layer=comp&fit=constrain,1&wid={$size}&hei={$size}fmt=jpg";
    }

    public function reviews($product_id,$product_site_id){

        $reviews_endpoint = $this->endpoints->reviews . $product_site_id;

        $this->logger->debug("Reviews Products ID: $product_site_id");

        // if($this->env == "dev"){
            $reviews_response = file_get_contents(__DIR__."/../../Data/Asda/Reviews.json");
            $reviews_results = $this->request->parse_json($reviews_response);
            $this->process_reviews($product_id, $reviews_results->Results);
        // } else {
        //     $reviews_response = $this->request->request($reviews_endpoint);
        //     $reviews_results = $this->request->parse_json($reviews_response);

        //     $total_reviews = $reviews_results->TotalResults;
        //     $this->logger->notice($total_reviews . ' Reviews Found');

        //     if($total_reviews > 100){
        //         $total_pages = ceil($total_reviews / 100 );
        //     } else {
        //         $total_pages = 1;
        //     }

        //     $this->logger->notice("Total Review Pages: $total_pages");

        //     for($review_page = 0;$review_page < $total_pages;$review_page++){

        //         $this->logger->debug("Reviews Page $review_page");

        //         $reviews_response = $this->request->request($reviews_endpoint . '&Limit=100&Offset=' . $review_page * 100);
        //         $reviews_results = $this->request->parse_json($reviews_response);

        //         $this->process_reviews($product_id, $reviews_results->Results);
        //     }
            
        // }

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
            }

        }

    }

    public function ingredients($product_id, $product_data){
        //Store Product Ingredients
        $ingredients_response = $product_data->item_enrichment->enrichment_info->ingredients_formatted;
        $ingredients_list = explode(' , ',$ingredients_response);

        print_r($ingredients_list);

        foreach($ingredients_list as $ingredient_item){
            $ingredient_name = preg_replace('/\s*\.?\s*$/','',$ingredient_item);
            if($ingredient_name != ''){
                $ingredient = new IngredientModel($this->database);
                $ingredient->name = $ingredient_name;
                $ingredient->product_id = $product_id;
    
                $ingredient->save();
            }

        }

    }

}

?>