<?php

namespace Supermarkets\Asda\Groceries\Products;

use Exception;

use Supermarkets\Asda\Asda;

use Models\Category\ChildCategoryModel;
use Models\Product\ProductModel;
use Models\Product\IngredientModel;
use Models\Category\CategoryProductModel;

use Monolog\Logger;

use Services\Config;
use Services\Database;
use Services\Remember;

use Interfaces\ProductInterface;

class Products extends Asda implements ProductInterface {

    public $product_details, $promotions, $image;

    function __construct(Config $config, Logger $logger, Database $database, Remember $remember=null)
    {
        parent::__construct($config,$logger,$database,$remember);
        $this->promotions = new Promotions($this->config,$this->logger,$this->database,$this->remember);

        $this->child_category_model = new ChildCategoryModel($this->database);
    }

    public function create_product($product_site_id, $child_category, $parent_site_category_name=null){
        //Get product details for each product and insert into database.

        $this->logger->info("Product ID: $product_site_id");

        if(is_null($product_site_id)){
            throw new Exception('product_site_id required to create product');
        }

        $product_item = new ProductModel($this->database);
        $product_results = $product_item->where(['site_product_id' => $product_site_id])->get()[0] ?? null;

        $product_categories = new CategoryProductModel($this->database);

        if(is_null($product_results)){
            
            $this->logger->info("New Product Found: $product_site_id");

            $product_details  = $this->product_details($product_site_id);

            if(is_null($child_category)){

                if(is_null($parent_site_category_name)){
                    throw new Exception('Parent Category Id or Parent Category Name Required');
                }

                
                $category_details = $this->child_category_model->like(['name'=> "$parent_site_category_name%"])->get();

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


                    $child_category = new ChildCategoryModel();

                    $child_category->grand_parent_category_id = $category_info->grand_parent_category_id;
                    $child_category->parent_category_id = $category_info->parent_category_id;
                    $child_category->id = $category_info->child_category_id;
                }
                
            }

            if(!is_null($product_details)){

                $this->logger->notice('Adding New Product: '.$product_details->name);

                $this->database->start_transaction();
                
                $product_details->database = $this->database;
                $product_id = $product_details->save();

                $product_categories->product_id = $product_id;
                $product_categories->child_category_id = $child_category->id;
                $product_categories->parent_category_id = $child_category->parent_category_id;
                $product_categories->grand_parent_category_id = $child_category->grand_parent_category_id;

                $product_categories->save();

                $this->create_ingredients($product_id,$this->product_details);

                $this->logger->notice('Complete Product Added: ' . $product_details->name);

                $this->database->commit_transaction();

                return $product_id;
            } else {
                $this->logger->debug('Product Not Added');
            }

        } else { 
            $this->logger->info("Product Found In Database: $product_site_id");
            // If under new category, save that under multiple categories

            $results = $product_categories->where(['product_id' => $product_results->id, 'child_category_id' => $child_category->id])->get()[0] ?? null;
            if(is_null($results)){
                $this->logger->info("No Product Under Category: $product_site_id");
                $product_categories->product_id = $product_results->id;
                $product_categories->child_category_id = $child_category->id;
                $product_categories->parent_category_id = $child_category->parent_category_id;
                $product_categories->grand_parent_category_id = $child_category->grand_parent_category_id;
                $product_categories->save();
            }

        }

    }

    public function product_details($site_product_id, $ignore_image=false,$ignore_promotion=false): ?ProductModel {

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

        $product_results = $this->request->parse_json($product_response);

        $product_details = $product_results->data->uber_item->items[0];

        $item = $product_details->item;
        $name = $item->name;
        $item_enrichment = $product_details->item_enrichment->enrichment_info;

        $is_bundle_product = $product_details->is_bundle ?? false;
        if($is_bundle_product){
            return $this->logger->debug('Bundle Product Found');
            return null;
        }

        $this->logger->notice('----- Start Product('.$item->sku_id.'): '.$item->name .' -----');

        $product = new ProductModel();
        $product->name = $this->clean_product_name($name);
        $product->available = 1;
        $product->dietary_info = $item_enrichment->dietary_info_formatted == '' ? NULL : $item_enrichment->dietary_info_formatted;

        if(is_null($product_details->price)){
            $this->logger->notice('Product Not Available. No Price Details Found.');
            $product->available = 0;
            return null;
        }

        $this->set_product_description($product, $item);

        $this->set_product_details($product, $item_enrichment, $item, $ignore_image);

        $this->set_product_prices($product, $product_details, $ignore_promotion);

        $this->product_details = $product_details;

        $this->logger->notice('----- Complete Product('.$item->sku_id.'): '.$item->name .' -----');

        return $product;
    }

    public function create_ingredients($product_id, $product_data){
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
            $ingredient_name = preg_replace('/\s*\.?\s*$|\n|\t/','',$ingredient_item);
            if($ingredient_name != ''){
                $list[] = $ingredient_name;
            }

        }

        //Return All Unique Ingredients
        return array_unique($list);
    }

    public function product_image($product_site_id, $image_id,$size,$size_name){
        $url = "https://ui.assets-asda.com/dm/asdagroceries/{$image_id}?defaultImage=asdagroceries/noImage&resMode=sharp2&id=8daSB3&fmt=jpg&fit=constrain,1&wid={$size}&hei={$size}";
        $file_name = $this->image->save($product_site_id,$url,$size_name);
        return $file_name;
    }
    
    private function clean_product_name($name){
        $name = preg_replace('/\s\s/',' ',$name);
        return preg_replace('/\s*\(.+/','',$name);
    }

    private function set_product_description($product, $item){
        // $product->store_type_id = $this->store_type_id;
        $product->description = $item->description == '.' ? NULL : $item->description;

        if(!is_null($product->description)){
            preg_match('/Twitter|YouTube|Instagram|Follow|Facebook|Snapchat|Shop online at asda.com/i',$product->description,$social_matches);

            // If product description like follow us on instagram then remove it. No need for such nonsense here
            if($social_matches){
                $product->description = NULL;
            }
        } else {
            if(property_exists($product,'additional_info') && $product->additional_info != ''){
                $product->description = $product->additional_info;
            }
        }
    }

    private function set_product_prices($product, $product_details, $ignore_promotion){
        // Promotion Types:
        // 1. 2 for £10. Product Grouped
        // 2. Rollback
        // 3. Sale.

        if(!$ignore_promotion){
            //This will get product price, regardless of promotions or not
            $product_prices = $this->promotions->product_prices($product_details);

            $product->price = $product_prices->price;
            $product->old_price = $product_prices->old_price ?? null;
            $product->is_on_sale = $product_prices->is_on_sale ?? null;
            $product->promotion_id = $product_prices->promotion_id ?? null;
            $product->promotion = $product_prices->promotion ?? null;
            
            // $product->promotion = null;
            // $product->promotion_id = null;
        }
    }

    private function set_product_details(ProductModel $product, $item_enrichment, $item, $ignore_image){

        $rating_review = $item->rating_review;

        $product_site_id = $item->sku_id;
        $product->site_product_id = $product_site_id;
        $product->store_type_id = $this->store_type_id;

        $product->currency = $this->currency;

        $product->total_reviews_count = $rating_review->total_review_count;
        $product->avg_rating          = $rating_review->avg_star_rating;

        $product->url = "https://groceries.asda.com/product/{$item->sku_id}";

        $image_id = $item->images->scene7_id;

        if(!$ignore_image){
            $product->large_image = $this->product_image($product_site_id, $image_id, 600,'large');
            if(!is_null($product->large_image)){
                $product->small_image = $this->product_image($product_site_id, $image_id, 200, 'small');
            }
        }

        $product->brand = $item->brand;
        $product->allergen_info = $item_enrichment->allergy_info_formatted_web == '' ? NULL : $item_enrichment->allergy_info_formatted_web ;

        $product->storage = $item_enrichment->storage ?? NULL;

        if($item->extended_item_info->weight){
            $product->weight = $item->extended_item_info->weight;
        }

    }

}

?>