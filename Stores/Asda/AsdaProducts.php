<?php

namespace Stores\Asda;

use Models\Category\ChildCategoryModel;
use Models\Product\ProductModel;
use Models\Product\IngredientModel;
use Exception;
use Models\Category\CategoryProductModel;
use Monolog\Logger;
use Shared\Config;
use Shared\Database;
use Shared\Remember;

class AsdaProducts extends Asda {

    public $product_details,$promotions,$image;

    function __construct(Config $config, Logger $logger, Database $database, Remember $remember=null)
    {
        parent::__construct($config,$logger,$database,$remember);
        $this->promotions = new AsdaPromotions($this->config,$this->logger,$this->database,$this->remember);
    }

    public function product($product_site_id,$grand_parent_category_id=null, $parent_category_id=null, $child_category_id=null,$parent_site_category_name=null){
        //Get product details for each product and insert into database.

        $this->logger->info("Product ID: $product_site_id");

        $product_item = new ProductModel($this->database);
        $product_results = $product_item->where(['site_product_id' => $product_site_id])->get()[0] ?? null;

        $product_categories = new CategoryProductModel($this->database);

        if(is_null($product_results)){
            
            $this->logger->info("New Product Found: $product_site_id");

            $product_details  = $this->product_details($product_site_id);

            if(is_null($parent_category_id)){

                if(is_null($parent_site_category_name)){
                    throw new Exception('Parent Category Id or Parent Category Name Required');
                }

                $category = new ChildCategoryModel($this->database);
                $category_details = $category->like(['name'=> "$parent_site_category_name%"])->get();

                if(count($category_details) > 0){
                    $category_details = $category_details[0];
                }

                if(!$category_details){
                    $this->logger->error('Failed To Find Matching Parent Category');
                    return;
                } else {

                    $category_info = $product_categories->where(['child_category_id' => $category_details->id])->get()[0] ?? null;

                    if(is_null($category_info)){
                        $this->logger->error('Failed To Find Product Category Details. ID:'.$category_details->id );
                        return;
                    }

                    $grand_parent_category_id = $category_info->grand_parent_category_id;
                    $parent_category_id = $category_info->parent_category_id;
                    $child_category_id = $category_info->child_category_id;
                }
                
            }

            if(!is_null($product_details)){

                $this->logger->notice("Adding New Product: ".$product_details->name);

                $this->database->start_transaction();
                
                $product_details->database = $this->database;
                $product_id = $product_details->save();

                $product_categories->product_id = $product_id;
                $product_categories->child_category_id = $child_category_id;
                $product_categories->parent_category_id = $parent_category_id;
                $product_categories->grand_parent_category_id = $grand_parent_category_id;

                $product_categories->save();

                $this->ingredients($product_id,$this->product_details);

                $this->logger->notice("Complete Product Added: " . $product_details->name);

                $this->database->commit_transaction();

                return $product_id;
            } else {
                $this->logger->debug('Product Not Added');
            }

        } else { 
            $this->logger->info("Product Found In Database: $product_site_id");
            // If under new category, save that under multiple categories

            $results = $product_categories->where(['product_id' => $product_results->id, 'child_category_id' => $child_category_id])->get()[0] ?? null;
            if(is_null($results)){
                $this->logger->info("No Product Under Category: $product_site_id");
                $product_categories->product_id = $product_results->id;
                $product_categories->child_category_id = $child_category_id;
                $product_categories->parent_category_id = $parent_category_id;
                $product_categories->grand_parent_category_id = $grand_parent_category_id;
                $product_categories->save();
            }

        }

    }

    public function product_details($site_product_id, $ignore_image=false){

        $shelf_endpoint = $this->endpoints->products;
        $this->logger->debug("Product Details ID: $site_product_id");

        if($this->env == 'dev'){
            $product_response = file_get_contents(__DIR__."/../../Data/Asda/Product.json");
        } else {

            //After running for about an hour this will fail. In that case, wait five minutes and retry
            $product_response = $this->request->request($shelf_endpoint,"POST",[
                "item_ids" => [$site_product_id], 
                "consumer_contract" => "webapp_pdp",
                "store_id" => "4676", // Change for different regions
                "request_origin" => "gi"
            ]);

        }
        
        if($product_response){
            $this->logger->debug('Product Returned');
        } else {
            $this->logger->debug('No Product Returned');
            return;
        }
        
        // file_put_contents(__DIR__.'/../../ProductDetails.json',$product_response);
        // $this->logger->debug('Saving File and Parse Json Response');

        //Get all product details and set them accordingly
        $product_results = $this->request->parse_json($product_response);

        $product_details = $product_results->data->uber_item->items[0];

        $item = $product_details->item;
        $name = $item->name;
        $item_enrichment = $product_details->item_enrichment->enrichment_info;
        $rating_review = $item->rating_review;

        $is_bundle_product = $product_details->is_bundle ?? false;
        if($is_bundle_product){
            return $this->logger->debug('Bundle Product Found');
            return;
        }

        $this->logger->notice('--- Start Product('.$item->sku_id.'): '.$item->name .' ---');

        $product = new ProductModel();
        $product->name = $this->clean_product_name($name);
        $product->dietary_info = $item_enrichment->dietary_info_formatted ?? NULL;

        if(property_exists($item_enrichment,'alcohol') && $item_enrichment->alcohol != ""){
            $this->logger->debug('Haram Alcholol Product Found: '. $name);
            return;
        }

        if(!$this->exclude_product($name)){
            $this->logger->debug('Stage 1. Product Not Exluded: '. $name);
        } else {
            $this->logger->debug('Stage 1. Product Exluded: '. $name);
            return null;
        }

        preg_match("/halal/i",$product->dietary_info,$halal_matches);
        preg_match("/vegetarian|vegan/i",$product->dietary_info,$vegan_matches);

        //Check product name, if matches possible haram then double check
        if(!$this->product_possible_haram($name)){
            $this->logger->debug('Stage 2. Product Halal '. $name);
        } else {
            
            $this->logger->debug('Stage 2. Product Maybe Haram. Halal/Vegan/Vegetarian Check');

            if(!is_null($product->dietary_info)){
                if($halal_matches || $vegan_matches){
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
            //Check product ingredients, if pork/alcohol found then exlucde.
            $ingredients = $this->ingredients_list($product_details);
            if($this->haram_ingredients($ingredients)){
                $this->logger->debug('Stage 3. Haram Ingredients Found: '. $name);
                return;
            } else {
                $this->logger->debug('Stage 3. No Haram Ingredients Found: '. $name);
            }
        } else {
            $this->logger->debug('Halal/Vegan/Vegetarian Found In Product Name');
        }

        // $product->store_type_id = $this->store_type_id;
        $product->description = $item->description == '.' ? NULL : $item->description;
        
        if(!is_null($product->description)){
            preg_match('/Twitter|YouTube|Instagram|Follow|Facebook|Snapchat|Shop online at asda.com/i',$product->description,$social_matches);

            // If product description like follow us on instagram then remove it. No need for such nonsense here
            if($social_matches){
                $product->description = NULL;
            }
        } else {
            if(property_exists($product,'additional_info') && $product->additional_info != ""){
                $product->description = $product->additional_info;
            }
        }

        $product_site_id = $item->sku_id;
        $product->site_product_id = $product_site_id;
        $product->store_type_id = $this->store_type_id;

        $product->total_reviews_count = $rating_review->total_review_count;
        $product->avg_rating          = $rating_review->avg_star_rating;

        $product->url = "https://groceries.asda.com/product/{$item->sku_id}";

        $image_id = $item->images->scene7_id;

        if(!$ignore_image){
            $product->large_image = $this->product_image($product_site_id, $image_id,400,'large');
            if(!is_null($product->large_image)){
                $product->small_image = $this->product_image($product_site_id, $image_id,200,'small');
            }
        }

        $product->brand = $item->brand;
        $product->allergen_info = $item_enrichment->allergy_info_formatted_web ?? NULL;

        $product->storage = $item_enrichment->storage ?? NULL;

        if($item->extended_item_info->weight){
            $product->weight = $item->extended_item_info->weight;
        }

        // Promotion Types:
        // 1. 2 for £10. Product Grouped
        // 2. Rollback
        // 3. Sale.

        //This will get product price, regardless of promotions or not
        $product_prices = $this->promotions->product_prices($product_details);

        $product->price = $product_prices->price;
        $product->old_price = $product_prices->old_price;
        $product->is_on_sale = $product_prices->is_on_sale;
        $product->promotion_id = $product_prices->promotion_id ?? null;
        $product->promotion = $product_prices->promotion ?? null;

        // $product->promotion = null;
        // $product->promotion_id = null;

        $this->product_details = $product_details;

        $this->logger->notice('--- Complete Product('.$item->sku_id.'): '.$item->name .' ---');

        return $product;
    }

    public function product_image($product_site_id, $image_id,$size,$size_name){
        $url = "https://ui.assets-asda.com/dm/asdagroceries/{$image_id}?defaultImage=asdagroceries/noImage&resMode=sharp2&id=8daSB3&fmt=jpg&fit=constrain,1&wid={$size}&hei={$size}";
        $file_name = $this->image->save($product_site_id,$url,$size_name);
        return $file_name;
    }

    public function ingredients($product_id, $product_data){
        //Store Product Ingredients
        $ingredients_list = $this->ingredients_list($product_data);

        foreach($ingredients_list as $ingredient_name){
            $ingredient = new IngredientModel($this->database);
            $ingredient->name = $ingredient_name;
            $ingredient->product_id = $product_id;
            $ingredient->insert_ignore = true;

            $ingredient->save();
        }

    }

    public function ingredients_list($product_data){
        $ingredients_response = $product_data->item_enrichment->enrichment_info->ingredients_formatted ?? '';
        $ingredients_list = explode(' , ',$ingredients_response);

        $list = array();

        foreach($ingredients_list as $ingredient_item){
            $ingredient_name = preg_replace('/\s*\.?\s*$/','',$ingredient_item);
            if($ingredient_name != ''){
                $list[] = $ingredient_name;
            }

        }

        //Return All Unique Ingredients
        return array_unique($list);
    }

    private function clean_product_name($name){
        $name = preg_replace('/\s\s/',' ',$name);
        return preg_replace('/\s*\(.+/','',$name);
    }

}

?>