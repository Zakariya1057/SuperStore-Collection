<?php

namespace Supermarkets\Canadian_Superstore\Groceries\Products;

use Exception;
use Models\Category\CategoryProductModel;
use Models\Category\ChildCategoryModel;
use Models\Product\IngredientModel;
use Models\Product\ProductImageModel;
use Models\Product\ProductModel;
use Supermarkets\Canadian_Superstore\CanadianSuperstore;

class Products extends CanadianSuperstore {

    public function create_product($product_data, $category_details, $request_type = 'v2'){

        $product = new ProductModel($this->database);

        if($request_type == 'v2'){
            $product = $this->parse_product_v2($product_data);
        } else {
            $product = $this->parse_product_v3($product_data);
        }
            
        // $product = $this->product_details('1093-921-206');

        if(!is_null($product)){

            $product_results = $product->where(['site_product_id' => $product->site_product_id])->get()[0] ?? null;
            // $product_results = null;

            if(is_null($product_results)){
                $this->logger->debug('New Product Found. Storing In Database: ');
                $product_id = $product->save();

                $this->create_product_category($category_details, $product_id);
                
                $this->create_ingredients($product_id, $product_data);
                $this->create_images($product_id, $product->images);
            } else {
                $this->logger->debug('Duplicate Product Found. Inserting Category Group');
                $this->create_product_category($category_details, $product_results->id);
            }
        }

        // die('Complete');
    }

    private function create_product_category($category_details, $product_id){
        $product_categories = new CategoryProductModel($this->database);

        $product_categories->product_id = $product_id;
        $product_categories->child_category_id = $category_details->id;
        $product_categories->parent_category_id = $category_details->parent_category_id;
        $product_categories->grand_parent_category_id = $category_details->grand_parent_category_id;
        $product_categories->insert_ignore = true;

        $product_categories->save();
    }

    private function create_ingredients($product_id, $product_details){

        $ingredients_text = $product_details->ingredients;

        if(is_null($ingredients_text)){
            return;
        }

        $ingredients = preg_split('/,|\./', trim($ingredients_text));

        foreach($ingredients as $ingredient_name){
            $ingredient = new IngredientModel($this->database);
            $ingredient->name = ucwords(strtolower($ingredient_name));
            $ingredient->product_id = $product_id;
            $ingredient->insert_ignore = true;
            $ingredient->save();
        }

    }

    private function create_images($product_id, $images){
        foreach($images as $image){
            $image->product_id = $product_id;
            $image->insert_ignore = true;
            $image->save();
        }
    }

    public function product_details($product_site_id): ?ProductModel{

        $product_endpoints = $this->endpoints->products;

        $endpoint_v3 = $product_endpoints->v3 . "$product_site_id?lang=en&date=13032021&storeId=2800&banner=superstore";
        $endpoint_v2 = $product_endpoints->v2 . $product_site_id;
        
        $product_response = null;
        $product = null;

        try {
            $product_response = $this->request->request($endpoint_v3, 'GET', [], ['x-apikey' => '1im1hL52q9xvta16GlSdYDsTsG0dmyhF'], 300, 1);
            $product_details = $this->request->parse_json($product_response);

            $product = $this->parse_product_v3($product_details);
        } catch (Exception $e){
            $this->logger->debug('Product V3 Endpoint Error: ' . $product_site_id . ' -> ' . $e->getMessage());
        }
        
        if(is_null($product_response)){
            $product_response = $this->request->request($endpoint_v2, 'GET', [], [], 300, 1);
            $product_details = $this->request->parse_json($product_response);
            $product = $this->parse_product_v2($product_details);
        }
        
        return $product;
    }


    private function parse_product_v2($product_details): ?ProductModel {
        $product = new ProductModel($this->database);

        $product->name = $product_details->title;
        $product->description = $this->set_description($product, $product_details->longDescription);
        $product->brand = $product_details->brand;

        $variant = $product_details->variants[0];
        $price_details = $variant->offers[0];

        $product->price = $price_details->price;

        if(!is_null($price_details->salePrice)){
            $product->is_on_sale = true;
            $product->price = $price_details->salePrice;
            $product->old_price = $price_details->wasPrice;

            die('Promotion Code Required');
        }

        $product->store_type_id = $this->store_type_id;
        $product->site_product_id = $product_details->productId;

        $product->images = [];

        foreach($price_details->media->images as $index => $image_url){
            if($index == 0){
                $product->small_image = $this->create_image($product->site_product_id, $image_url, 'small');
                $product->large_image = $this->create_image($product->site_product_id, $image_url, 'large');
            } else {
                $image = new ProductImageModel($this->database);
                $image->name = $this->create_image($product->site_product_id . '_' . $index, $image_url, 'large');
                $image->size = "large"; 
                $product->images[] = $image;
            }
        }
        
        $product->currency = $this->currency;

        $product->url = "https://www.realcanadiansuperstore.ca" . $product_details->uri;

        return $product;
    }

    private function parse_product_v3($product_details): ?ProductModel {
        $product = new ProductModel($this->database);

        $product->name = $product_details->name;
        $product->site_product_id = $product_details->code;
        $product->store_type_id = $this->store_type_id;
        
        $product->weight = $product_details->packageSize;
        $product->currency = $this->currency;
        
        $product->price = $product_details->prices->price->value;

        $product->description = $this->set_description($product, $product_details->description);
        $product->brand = $product_details->brand;
        $product->url = "https://www.realcanadiansuperstore.ca" . $product_details->link;

        $product->images = [];

        foreach($product_details->imageAssets as $index => $image_asset){
            if($index == 0){
                $product->small_image = $this->create_image($product->site_product_id, $image_asset->smallUrl, 'small');
                $product->large_image = $this->create_image($product->site_product_id, $image_asset->smallUrl, 'large');
            } else {
                $image = new ProductImageModel($this->database);
                $image->name = $this->create_image($product->site_product_id . '_' . $index, $image_asset->smallUrl, 'large');
                $image->size = "large"; 
                $product->images[] = $image;
            }
        }

        return $product;
    }

    private function create_image($name, $url, $size): ?string {
        return $this->image->save($name, $url, $size, "products", $this->store_name);
    }

    private function set_description(ProductModel $product, $description) {
        preg_match('/(.+)Features(.+)Dimensions(.+)/i', $description, $matches);

        if($matches){
            $start_description = $matches[1];
            $features = $matches[2];
            $dimensions = $matches[3];
            
            $product->features = $this->create_description($features);
            $product->dimensions = $this->create_description($dimensions);

            $product->description = $this->create_description($start_description);
        } else {
            $product->description = $description;
        }

    }

    private function create_description($description){
        $description_list = $this->seperate_description($description);

        $description_output = [];

        foreach($description_list as $description_item){
            $item = $this->clean_description_name($description_item);

            if($item != ''){
                $description_output[] = $item;
            }
        }
        
        return json_encode($description_output);
    }

    private function seperate_description($description){
        $description = preg_replace('/<\/*\S+>/',"\n", $description);
        return explode("\n", $description);
    }

    private function clean_description_name($description){
        $description = preg_replace('/^-\s+|^:|Specifications\//','', $description);
        $description = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "", $description);
        $description = $this->sanitize->sanitize_field($description);
        return strip_tags($description);
    }
    

}

?>