<?php

namespace Stores\Asda;

use Models\Category\ChildCategoryModel;
use Models\Product\ProductModel;
use Models\Product\ReviewModel;
use Models\Product\IngredientModel;
use Exception;

class AsdaProducts extends Asda {

    public $product_details,$promotions;

    function __construct($config,$logger,$database,$remember)
    {
        parent::__construct($config,$logger,$database,$remember);
        $this->promotions = new AsdaPromotions($this->config,$this->logger,$this->database,$this->remember);
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

                if(is_array($category_details)){
                    //Multiple Category Match
                    $this->logger->debug('Multiple Possible Category Match With Name: ' . $parent_site_category_name);
                    $this->logger->debug('Select First Product In List');
                    $category_details = $category_details[0];
                }

                if(!$category_details){
                    throw new Exception('Failed To Find Matching Parent Category');
                } else {
                    $parent_category_id =  $category_details->id;
                }
                
            }

            if($product_details){

                $this->logger->notice("Adding New Product: ".$product_details->name);

                $this->database->start_transaction();
                
                //Product Added
                $product_details->parent_category_id = $parent_category_id;
                $product_details->database = $this->database;
                $product_id = $product_details->save();

                // $this->reviews($product_id,$product_site_id);
                // Product Reviews, Product Recommended Scraped Seperately

                $this->ingredients($product_id,$this->product_details);

                $this->logger->notice("Complete Product Added: " . $product_details->name);

                $this->database->end_transaction();

                return $product_id;
            } else {
                $this->logger->debug('Product Not Added');
            }

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

            //After running for about an hour this will fail. In that case, wait five minutes and retry
            $product_response = $this->request->request($shelf_endpoint,"POST",[
                "item_ids" => [$product_id], 
                "consumer_contract" => "webapp_pdp",
                "store_id" => "4676",
                "request_origin" => "gi"
            ]);

        }
        
        if($product_response){
            $this->logger->debug('Product Returned');
        } else {
            $this->logger->debug('No Product Returned');
        }
        
        file_put_contents(__DIR__.'/../../ProductDetails.json',$product_response);

        $this->logger->debug('Saving File and Parse Json Response');
        //Get all product details and set them accordingly
        $product_results = $this->request->parse_json($product_response);
        $this->logger->debug('Successfully parsed json file');

        $product_details = $product_results->data->uber_item->items[0];
        $this->logger->debug('Fetched product details');

        $item = $product_details->item;
        $name = $item->name;
        $item_enrichment = $product_details->item_enrichment->enrichment_info;
        $rating_review = $item->rating_review;

        $this->logger->notice('--- Start Product('.$item->sku_id.'): '.$item->name .' ---');

        $product = new ProductModel();
        $product->name = $name;
        $product->dietary_info = $item_enrichment->dietary_info_formatted ?? NULL;

        if(!$this->exclude_product($name)){
            $this->logger->debug('Stage 1. Product Not Exluded: '. $name);
        } else {
            $this->logger->debug('Stage 1. Product Exluded: '. $name);
            return null;
        }

        preg_match("/halal|vegetarian|vegan/i",$product->dietary_info,$halal_matches);

        //Check product name, if matches possible haram then double check
        if(!$this->product_possible_haram($name)){
            $this->logger->debug('Stage 2. Product Halal '. $name);
        } else {
            
            $this->logger->debug('Stage 2. Product Maybe Haram. Halal/Vegan/Vegetarian Check');

            if(!is_null($product->dietary_info)){
                if($halal_matches){
                    $this->logger->debug('Stage 2A. Product Halal');
                } else {
                    $this->logger->debug('Stage 2. Product Haram: '. $name);
                    return;
                }
            } else {
                $this->logger->debug('Stage 2. Product Haram: '. $name);
                return;
            }
           
        }

        if(!$halal_matches){
            //Check product ingredients, if pork found then exlucde.
            $ingredients = $this->ingredients_list($product_details);
            if($this->haram_ingredients($ingredients)){
                $this->logger->debug('Stage 3. Haram Ingredients Found: '. $name);
                return;
            } else {
                $this->logger->debug('Stage 3. No Haram Ingredients Found: '. $name);
            }
        } else {
            $this->logger->debug('Halal Found In Product Name');
        }

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

    public function ingredients($product_id, $product_data){
        //Store Product Ingredients

        $ingredients_list = $this->ingredients_list($product_data);

        // print_r($ingredients_list);

        foreach($ingredients_list as $ingredient_name){
            $ingredient = new IngredientModel($this->database);
            $ingredient->name = $ingredient_name;
            $ingredient->product_id = $product_id;
            $ingredient->insert_ignore = true;

            $ingredient->save();
        }

    }

    public function ingredients_list($product_data){
        $ingredients_response = $product_data->item_enrichment->enrichment_info->ingredients_formatted;
        $ingredients_list = explode(' , ',$ingredients_response);

        $list = array();

        foreach($ingredients_list as $ingredient_item){
            $ingredient_name = preg_replace('/\s*\.?\s*$/','',$ingredient_item);
            if($ingredient_name != ''){
                $list[] = $ingredient_name;
            }

        }

        return array_unique($list);
    }

}

?>