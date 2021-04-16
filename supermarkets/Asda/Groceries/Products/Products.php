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
use Models\Product\BarcodeModel;

class Products extends Asda implements ProductInterface {

    public $promotions, $image;

    function __construct(Config $config, Logger $logger, Database $database, Remember $remember=null)
    {
        parent::__construct($config,$logger,$database,$remember);
        $this->promotions = new Promotions($this->config,$this->logger,$this->database,$this->remember);

        $this->child_category_model = new ChildCategoryModel($this->database);
    }

    public function create_product($site_product_id, $child_category, $parent_site_category_name=null){
        //Get product details for each product and insert into database.

        $site_product_id = '1000134959131';

        $this->logger->info("Product ID: $site_product_id");

        if(is_null($site_product_id)){
            throw new Exception('site_product_id required to create product');
        }

        $product_item = new ProductModel($this->database);
        $product_results = $product_item->where(['site_product_id' => $site_product_id])->get()[0] ?? null;

        $product_categories = new CategoryProductModel($this->database);

        if(is_null($product_results)){
            
            $this->logger->info("New Product Found: $site_product_id");

            $product_details  = $this->product_details($site_product_id);

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

                $this->create_promotion($product_details);

                $product_id = $product_details->save();

                $product_categories->product_id = $product_id;
                $product_categories->child_category_id = $child_category->id;
                $product_categories->parent_category_id = $child_category->parent_category_id;
                $product_categories->grand_parent_category_id = $child_category->grand_parent_category_id;

                $product_categories->save();

                $this->create_ingredients($product_id, $product_details->ingredients);

                $this->create_barcodes($product_id, $product_details);

                $this->logger->notice('Complete Product Added: ' . $product_details->name);

                $this->database->commit_transaction();

                return $product_id;
            } else {
                $this->logger->debug('Product Not Added');
            }

        } else { 
            $this->logger->info("Product Found In Database: $site_product_id");
            // If under new category, save that under multiple categories

            $results = $product_categories->where(['product_id' => $product_results->id, 'child_category_id' => $child_category->id])->get()[0] ?? null;
            if(is_null($results)){
                $this->logger->info("No Product Under Category: $site_product_id");
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
        $inventory = $product_details->inventory;

        $is_bundle_product = $product_details->is_bundle ?? false;
        if($is_bundle_product){
            return $this->logger->debug('Bundle Product Found');
            return null;
        }

        $this->logger->notice('----- Start Product('.$item->sku_id.'): '.$item->name .' -----');

        $product = new ProductModel();
        $product->name = $this->clean_product_name($name);
        $product->available = 1;

        if(is_null($product_details->price)){
            $this->logger->notice('Product Not Available. No Price Details Found.');
            $product->available = 0;
            return null;
        }

        $this->set_product_description($product, $item, $item_enrichment);

        $this->set_product_details($product, $item_enrichment, $item, $inventory, $ignore_image);

        $this->set_product_prices($product, $product_details, $ignore_promotion);

        $this->set_ingredients($product, $product_details);

        $this->logger->notice('----- Complete Product('.$item->sku_id.'): '.$item->name .' -----');

        return $product;
    }

    public function create_ingredients($product_id, $ingredients){
        //Store Product Ingredients

        foreach($ingredients as $ingredient_name){
            $ingredient = new IngredientModel($this->database);
            $ingredient->name = $ingredient_name;
            $ingredient->product_id = $product_id;
            $ingredient->insert_ignore = true;

            $ingredient->save();
        }

    }

    public function set_ingredients($product, $product_data){
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
        $product->ingredients = array_unique($list);
    }

    public function create_promotion(ProductModel &$product){
        if(property_exists($product, 'promotion') && !is_null($product->promotion)){
            if(property_exists($product->promotion, 'id')){
                $product->promotion_id = $product->promotion->id;
            } else {
                $product->promotion_id = $product->promotion->save();
            }
        }
    }

    public function create_barcodes($product_id, $product){
        foreach($product->barcodes as $barcode){
            $barcode->product_id = $product_id;
            $barcode->save();
        }
    }

    public function product_image($site_product_id, $image_id,$size,$size_name){
        $url = "https://ui.assets-asda.com/dm/asdagroceries/{$image_id}?defaultImage=asdagroceries/noImage&resMode=sharp2&id=8daSB3&fmt=jpg&fit=constrain,1&wid={$size}&hei={$size}";
        $file_name = $this->image->save($site_product_id,$url,$size_name);
        return $file_name;
    }
    
    private function clean_product_name($name){
        $name = preg_replace('/\s\s/',' ',$name);
        return preg_replace('/\s*\(.+/','',$name);
    }

    private function set_product_description($product, $item, $item_enrichment){
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
            } elseif(property_exists($item_enrichment, 'product_marketing') && $item_enrichment->product_marketing != ''){
                $product->description = $item_enrichment->product_marketing;
            }
        }

        $product->description = trim($product->description) == '' ? NULL : $product->description;
    }

    private function set_product_prices($product, $product_details, $ignore_promotion){
        // Promotion Types:
        //
        // 1. 2 for £10. Product Grouped
        // 2. Rollback
        // 3. Sale.

        if(!$ignore_promotion){

            //This will get product price, regardless of promotions or not
            $product_prices = $this->promotions->product_prices($product_details);

            $product->price = $product_prices->price;
            $product->old_price = $product_prices->old_price ?? null;
            $product->is_on_sale = $product_prices->is_on_sale ?? null;
            $product->promotion = $product_prices->promotion ?? null;
        }
    }

    private function set_product_details(ProductModel $product, $item_enrichment, $item, $inventory, $ignore_image){

        $rating_review = $item->rating_review;

        $site_product_id = $item->sku_id;
        $product->site_product_id = $site_product_id;
        $product->store_type_id = $this->store_type_id;

        $this->set_barcodes($product, $item, $inventory);

        $product->currency = $this->currency;

        $product->total_reviews_count = $rating_review->total_review_count;
        $product->avg_rating          = $rating_review->avg_star_rating;

        $product->url = "https://groceries.asda.com/product/{$item->sku_id}";

        $image_id = $item->images->scene7_id;

        if(!$ignore_image){
            $product->large_image = $this->product_image($site_product_id, $image_id, 600,'large');
            if(!is_null($product->large_image)){
                $product->small_image = $this->product_image($site_product_id, $image_id, 200, 'small');
            }
        }

        $product->brand = $item->brand;

        $this->set_optional_details($product, $item_enrichment);

        if($item->extended_item_info->weight){
            $product->weight = $item->extended_item_info->weight;
        }
    }

    private function set_optional_details(&$product, $item_enrichment){

        $optional_details = [
            'safety_warning' => 'warning',
            'country_of_origin' => 'country_of_origin',
            'allergy_info_formatted_web' => 'allergen_info',
            'storage' => 'storage',
            'dietary_info_formatted' => 'dietary_info'
        ];

        foreach($optional_details as $property => $new_property){
            if(property_exists($item_enrichment, $property)){
                $product->{$new_property} = trim($item_enrichment->{$property}) == '' ? NULL : $item_enrichment->{$property};
            }
        }

    }

    public function set_barcodes(&$product, $item, $inventory){
        $barcodes_data = [
            'cin' => $inventory->cin,
        ];

        foreach($item->upc_numbers as $upc){
            $barcodes_data['upc'] = $upc;
        }

        $product->barcodes = [];
        foreach($barcodes_data as $type => $value){

            if(!is_null($value) && $value != ''){
                $barcode = new BarcodeModel($this->database);
                $barcode->type = $type;
                $barcode->value = $value;
                $barcode->store_type_id = $this->store_type_id;
    
                $product->barcodes[] = $barcode;
            }
        }
    }

}

?>