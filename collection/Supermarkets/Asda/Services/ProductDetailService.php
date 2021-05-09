<?php

namespace Collection\Supermarkets\Asda\Services;

use Collection\Supermarkets\Asda\Asda;
use Models\Product\ProductModel;
use Models\Product\BarcodeModel;
use Models\Product\IngredientModel;

class ProductDetailService extends Asda {

    private $promotion_service;

    private function setupPromotionService(){
        if(is_null($this->promotion_service)){
            $this->promotion_service = new PromotionService($this->config, $this->logger, $this->database);
        }
    }

    public function set_ingredients($product, $product_data){
        $ingredients_response = $product_data->item_enrichment->enrichment_info->ingredients_formatted ?? '';
        $ingredients_list = explode(' , ',$ingredients_response);

        $unique_ingredients = [];
        $ingredients = [];

        foreach($ingredients_list as $ingredient_item){
            $ingredient_name = preg_replace('/\s*\.?\s*$|\n|\t/','',$ingredient_item);
            if(!key_exists($ingredient_item, $unique_ingredients)){
                $unique_ingredients[$ingredient_name] = 1;

                $ingredient = new IngredientModel($this->database);
                $ingredient->name = $ingredient_name;

                $ingredients[] = $ingredient;
            }
        }

        $product->ingredients = $ingredients;
    }

    public function product_image($site_product_id, $image_id,$size,$size_name){
        $url = "https://ui.assets-asda.com/dm/asdagroceries/{$image_id}?defaultImage=asdagroceries/noImage&resMode=sharp2&id=8daSB3&fmt=jpg&fit=constrain,1&wid={$size}&hei={$size}";
        $file_name = $this->image->save($site_product_id,$url,$size_name);
        return $file_name;
    }
    
    public function clean_product_name($name){
        $name = preg_replace('/\s\s/',' ',$name);
        return preg_replace('/\s*\(.+/','',$name);
    }

    public function set_product_group(&$product, $product_details){
        $site_product_group_id = $product_details->item->taxonomy_info->shelf_id;
        $product_group_name = $product_details->item->taxonomy_info->shelf_name;

        $product->product_group = (object)['id' => $site_product_group_id, 'name' => $product_group_name];
    }

    public function set_product_description($product, $item, $item_enrichment){
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

    public function set_product_prices($product, $product_details, $ignore_promotion){
        // Promotion Types:
        // 1. 2 for £10. Product Grouped
        // 2. Rollback
        // 3. Sale.

        if(!$ignore_promotion){

            $this->setupPromotionService();

            //This will get product price, regardless of promotions or not
            $product_prices = $this->promotion_service->product_prices($product_details);

            $product->price = $product_prices->price;
            $product->old_price = $product_prices->old_price ?? null;
            $product->is_on_sale = $product_prices->is_on_sale ?? null;
            $product->promotion = $product_prices->promotion ?? null;
        }
    }

    public function set_product_details(ProductModel $product, $item_enrichment, $item, $inventory, $ignore_image){

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

    public function set_optional_details(&$product, $item_enrichment){

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