<?php

namespace Supermarkets\Canadian_Superstore\Groceries\Products;

use Exception;

use Supermarkets\Canadian_Superstore\CanadianSuperstore;

use Models\Category\CategoryProductModel;
use Models\Product\IngredientModel;
use Models\Product\ProductImageModel;
use Models\Product\ProductModel;

use Interfaces\ProductInterface;
use Models\Product\PromotionModel;

class Products extends CanadianSuperstore implements ProductInterface {

    private $promotions;

    public function create_product($site_product_id, $category_details, $request_type = null, $product = null){

        $this->database->start_transaction();

        $product_id = null;

        if(is_null($product)){
            $product = $this->product_details($site_product_id, false, $request_type);
        }
        
        if(!is_null($product)){
            $this->logger->debug("Start Creating Product: [{$product->site_product_id}] {$product->name}");

            $product_results = $product->where(['site_product_id' => $product->site_product_id])->get()[0] ?? null;

            if(is_null($product_results)){
                $this->logger->debug('New Product Found. Storing In Database: ' . $product->name);
                $product_id = $product->save();

                $this->create_product_category($category_details, $product_id);
                
                $this->create_ingredients($product_id, $product);
                $this->create_images($product_id, $product->images);
            } else {
                $this->logger->debug('Duplicate Product Found. Inserting Category Group');
                $product_id = $product_results->id;
                $this->create_product_category($category_details, $product_id);
            }

            $this->logger->debug("Complete Creating Product: [{$product->site_product_id}] {$product->name}");

        } else {
            $this->logger->debug("Product Not Found. Returning Null");
        }

        $this->database->commit_transaction();
        

        return $product_id;
        
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

    private function create_ingredients($product_id, $product){
        if(count($product->ingredients) > 0){
            foreach($product->ingredients as $ingredient){
                $ingredient->product_id = $product_id;
                $ingredient->insert_ignore = true;
                $ingredient->save();
            }
        }
    }

    private function create_images($product_id, $images){
        foreach($images as $image){
            $image->product_id = $product_id;
            $image->insert_ignore = true;
            $image->save();
        }
    }

    public function product_details($product_site_id, $ignore_image=false, $request_type = null): ?ProductModel{

        $product_response = null;
        $product = null;

        $this->logger->debug('Request Type: ' . $request_type);

        $product_endpoints = $this->endpoints->products;

        if(is_null($request_type) || $request_type == 'v3'){
            $endpoint_v3 = $product_endpoints->v3 . "$product_site_id?lang=en&storeId=1077&banner=superstore";
            
            try {
                $product_response = $this->request->request($endpoint_v3, 'GET', [], ['x-apikey' => '1im1hL52q9xvta16GlSdYDsTsG0dmyhF'], 300, 1);
                $product_details = $this->request->parse_json($product_response);
                $product = $this->parse_product_v3($product_details, $ignore_image);
    
            } catch (Exception $e){
                $this->logger->debug('Product V3 Endpoint Error: ' . $product_site_id . ' -> ' . $e->getMessage());
            }
        }
        
        if(is_null($product_response)){
            
            $endpoint_v2 = $product_endpoints->v2 . $product_site_id;

            try {
                $product_response = $this->request->request($endpoint_v2, 'GET', [], [], 300, 1);
                $product_details = $this->request->parse_json($product_response);
                $product = $this->parse_product_v2($product_details, $ignore_image);
            } catch(Exception $e){
                // $this->logger->error('Product Not Found On Either Endpoints: ' . $e->getMessage());
                throw new Exception('Product Not Found On Either Endpoints');
                // return null;
            }

        }
        
        return $product;
    }


    private function parse_product_v2($product_details, $ignore_image=false): ?ProductModel {
        $product = new ProductModel($this->database);

        $product->name = $product_details->title;
        $product->available = 1;

        $this->set_description($product, $product_details->longDescription);
        $product->brand = $product_details->brand;

        $variant = $product_details->variants[0];
        $price_details = $variant->offers[0];

        $product->price = $price_details->price;

        if(!is_null($price_details->salePrice)){
            $product->is_on_sale = true;
            $product->price = $price_details->salePrice;
            $product->old_price = $price_details->wasPrice;
        }

        $product->store_type_id = $this->store_type_id;
        $product->site_product_id = $product_details->productId;

        $product->images = [];
        $product->ingredients = [];

        if(!$ignore_image){
            foreach($price_details->media->images as $index => $image_url){
                if($index == 0){
                    $product->small_image = $this->create_image($product->site_product_id, $image_url, 'small');
                    $product->large_image = $this->create_image($product->site_product_id, $image_url, 'large');
                } else {
                    $image = new ProductImageModel($this->database);
    
                    $image_name = $this->create_image($product->site_product_id . '_' . $index, $image_url, 'large');
    
                    if(!is_null($image_name)){
                        $image->name = $image_name;
                        $image->size = "large"; 
                        $product->images[] = $image;
                    }
    
                }
            }
        }

        
        $product->currency = $this->currency;

        $product->url = "https://www.realcanadiansuperstore.ca" . $product_details->uri;

        return $product;
    }

    private function parse_product_v3($product_details, $ignore_image=false): ?ProductModel {
        $product = new ProductModel($this->database);

        $product->name = $product_details->name;
        $product->available = 1;
        $product->site_product_id = $product_details->code;
        $product->store_type_id = $this->store_type_id;
        
        $product->weight = $product_details->packageSize;
        $product->currency = $this->currency;
        
        $this->set_price_v3($product, $product_details);

        $this->set_description($product, $product_details->description);

        $product->brand = $product_details->brand;
        $product->url = "https://www.realcanadiansuperstore.ca" . $product_details->link;

        $product->images = [];
        $product->categories = [];

        foreach($product_details->breadcrumbs ?? [] as $category_data){
            $product->categories[] = $category_data->categoryCode;
        }

        $product->ingredients = [];

        $ingredients_text = $product_details->ingredients;
        if(!is_null($ingredients_text)){
            $ingredients = preg_split('/,|\./', trim($ingredients_text));

            foreach($ingredients as $ingredient_name){
                $ingredient = new IngredientModel($this->database);
                $name = trim(ucwords(strtolower($ingredient_name)));

                if($name != ''){
                    $ingredient->name = trim(ucwords(strtolower($ingredient_name)));
                    $product->ingredients[] = $ingredient;
                }

            }
        }

        if(!$ignore_image){
            foreach($product_details->imageAssets as $index => $image_asset){
                if($index == 0){
                    $product->small_image = $this->create_image($product->site_product_id, $image_asset->smallUrl, 'small');
                    $product->large_image = $this->create_image($product->site_product_id, $image_asset->mediumUrl, 'large');
                } else {
                    $image = new ProductImageModel($this->database);
    
                    $image_name = $this->create_image($product->site_product_id . '_' . $index, $image_asset->smallUrl, 'large');
    
                    if(!is_null($image_name)){
                        $image->name = $image_name;
                        $image->size = "large"; 
                        $product->images[] = $image;
                    }
                }
            }
        }

        return $product;
    }



    private function set_price_v3($product, $product_details){

        $price_details = $product_details->prices;

        $product->price = $price_details->price->value;

        $deal = $product_details->badges->dealBadge;

        if(!is_null($price_details->wasPrice)){
            $ends_at = $deal->expiryDate;

            $product->is_on_sale = true;
            $product->sale_ends_at = date("Y-m-d H:i:s", strtotime($ends_at));
            $product->old_price = $price_details->wasPrice->value;

        } else if(!is_null($product_details->badges->dealBadge)){
            if(is_null($this->promotions)){
                $this->promotions = new Promotions($this->config,$this->logger,$this->database,$this->remember);
            }

            $product->promotion_id = $this->promotions->parse_promotion_v3($deal);
        }

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
            $product->description = str_replace('..','.', preg_replace('/<\/*\w+>\s*<\/*\w+>/',".\n\n", $start_description));
        } else {
            $product->description = str_replace('..','.', preg_replace('/<\/*\w+>\s*<\/*\w+>/',".\n\n", $description));
        }

    }

    private function create_description($description){
        $description_list = $this->seperate_description($description) ?? [$description];

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