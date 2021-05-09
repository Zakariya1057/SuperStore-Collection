<?php

namespace Collection\Supermarkets\Canadian_Superstore\Groceries\Products;

use Collection\Services\SharedProductService;
use Exception;
use Interfaces\ProductInterface;
use Models\Product\ProductModel;

use Collection\Supermarkets\Canadian_Superstore\CanadianSuperstore;

use Monolog\Logger;
use Services\Config;
use Services\Database;
use Services\Remember;

use Collection\Supermarkets\Canadian_Superstore\Services\CategoryService;
use Collection\Supermarkets\Canadian_Superstore\Services\ProductDetailService;
use Collection\Supermarkets\Canadian_Superstore\Services\ProductGroupService;
use Collection\Supermarkets\Canadian_Superstore\Services\ProductService;


class Products extends CanadianSuperstore implements ProductInterface {
    private $product_v2, $product_v3;

    public $product_service, $category_service, $product_group_service;

    public $product_detail_service;

    function __construct(Config $config, Logger $logger, Database $database, Remember $remember=null)
    {
        parent::__construct($config,$logger,$database,$remember);

        $this->product_service = new SharedProductService(new ProductService($config,$logger,$database));

        $this->category_service = new CategoryService($config, $logger, $database);
        $this->product_group_service = new ProductGroupService($config, $logger, $database);

        $this->product_detail_service = new ProductDetailService($config, $logger, $database);
    }

    private function setupProductSources(){
        if(is_null($this->product_v2) || is_null($this->product_v3)){
            $this->product_v2 = new ProductV2($this->config, $this->logger, $this->database, $this->remember);
            $this->product_v3 = new ProductV3($this->config, $this->logger, $this->database, $this->remember);
        }
    }

    public function create_product($site_product_id, $category_details, $request_type = null){
        
        $this->database->start_transaction();

        // 1. Request Product Details
        // 2. Parse Response Into Model
        // 3, Create Product Model
        // 4. Save Product
        // 5. Create/Update Category Product

        $parsed_product = $this->product_details($site_product_id, false, $request_type);

        $product_id = $this->product_service->product_exists($site_product_id, $this->store_type_id);

        $product_group_id = $this->product_group_service->create($parsed_product, $category_details->id);

        if(is_null($product_id)){
            $product_id = $this->product_service->create_product($parsed_product);

            $this->product_service->create_promotion($parsed_product);

            $this->product_service->create_images($product_id, $parsed_product);
            $this->product_service->create_ingredients($product_id, $parsed_product);
            $this->product_service->create_barcodes($product_id, $parsed_product);

            $this->category_service->create($category_details, $product_id, $product_group_id);
        } else {
            if($this->category_service->category_exists($category_details, $product_id)){
                $this->category_service->update($category_details, $product_id, $product_group_id);
            } else {
                $this->category_service->create($category_details, $product_id, $product_group_id);
            }
        }

        $this->database->commit_transaction();

        return $product_id;
    }

    public function product_details($site_product_id, $ignore_image=false): ?ProductModel {
        $product_response = $this->product_service->request_product($site_product_id);

        $this->setupProductSources();

        if(!is_null($product_response)){
            if($product_response['type'] == 'v2'){
                $parsed_product = $this->product_v2->parse_product($product_response['response'], $ignore_image);
            } else {
                $parsed_product = $this->product_v3->parse_product($product_response['response'], $ignore_image);
            }
    
            return $parsed_product;
        } else {
            $this->logger->notice('Product Details Not Found: '. $site_product_id);
            return null;
        }

    }

    // public function create_products($site_product_id, $category_details, $request_type = null, $product = null){

    //     $product_model = new ProductModel($this->database);

    //     $this->database->start_transaction();

    //     $product = $this->product_details($site_product_id, false, $request_type);

    //     $product_group = $this->product_group->create($product, $category_details->id);

    //     // $site_product_id = $product_item->productId ?? $product_item->code;

    //     $product_id = null;

    //     // $site_product_id = '21189781_EA';

    //     $product_results = $product_model->where(['site_product_id' => $site_product_id])->get()[0] ?? null;

    //     if(is_null($product_results)){

    //         if(is_null($product)){
    //             // $product = $this->product_details($site_product_id, false, $request_type);
    //         }
            
    //         if(!is_null($product)){

    //             $this->logger->debug("Start Creating Product: [{$product->site_product_id}] {$product->name}");

    //             $this->logger->debug('New Product Found. Storing In Database: ' . $product->name);

    //             $this->create_promotion($product);

    //             $product_id = $product->save();

    //             $this->create_product_category($category_details, $product_id, $product_group->id);
                
    //             $this->create_barcodes($product_id, $product);

    //             $this->create_ingredients($product_id, $product);
    //             $this->create_images($product_id, $product->images);
    
    //             $this->logger->debug("Complete Creating Product: [{$product->site_product_id}] {$product->name}");
    
    //         } else {
    //             $this->logger->debug("Product Not Found. Returning Null");
    //         }
            
    //     } else {
    //         $product_id = $product_results->id;

    //         $this->logger->debug('Duplicate Product Found. Inserting Category Group');
    //         $this->create_product_category($category_details, $product_id, $product_group->id);
    //     }
        
    //     $this->database->commit_transaction();

    //     return $product_id;
        
    // }

    // private function create_product_category($category_details, $product_id, $product_group_id){
    //     $product_categories = new CategoryProductModel($this->database);

    //     $product_categories->product_id = $product_id;
    //     $product_categories->product_group_id = $product_group_id;
    //     $product_categories->child_category_id = $category_details->id;
    //     $product_categories->parent_category_id = $category_details->parent_category_id;
    //     $product_categories->grand_parent_category_id = $category_details->grand_parent_category_id;
    //     $product_categories->insert_ignore = true;

    //     $product_categories->save();
    // }

    // private function create_barcodes($product_id, $product){
    //     if(key_exists('barcodes', $product)){
    //         foreach($product->barcodes as $barcode){
    //             $barcode->product_id = $product_id;
    //             $barcode->save();
    //         }
    //     }
    // }

    // private function create_ingredients($product_id, $product){
    //     if(count($product->ingredients) > 0){
    //         foreach($product->ingredients as $ingredient){
    //             $ingredient->product_id = $product_id;
    //             $ingredient->insert_ignore = true;
    //             $ingredient->save();
    //         }
    //     }
    // }

    // public function create_promotion(ProductModel &$product){
    //     if(property_exists($product, 'promotion') && !is_null($product->promotion)){
    //         if(property_exists($product->promotion, 'id')){
    //             $product->promotion_id = $product->promotion->id;
    //         } else {
    //             $product->promotion_id = $product->promotion->save();
    //         }
    //     }
    // }

    // private function create_images($product_id, $images){
    //     foreach($images as $image){
    //         $image->product_id = $product_id;
    //         $image->insert_ignore = true;
    //         $image->save();
    //     }
    // }

    // public function product_details($product_site_id, $ignore_image=false, $request_type = null): ?ProductModel{

    //     $product_response = null;
    //     $product = null;

    //     $this->logger->debug('Request Type: ' . $request_type);

    //     $product_endpoints = $this->endpoints->products;

    //     $retry_times = !is_null($request_type) ? 3 : 1;

    //     if(is_null($request_type) || $request_type == 'v3'){
    //         $endpoint_v3 = $product_endpoints->v3 . "$product_site_id?lang=en&storeId=1077&banner=superstore";
            
    //         try {
    //             $product_response = $this->request->request($endpoint_v3, 'GET', [], ['x-apikey' => '1im1hL52q9xvta16GlSdYDsTsG0dmyhF'], 300, $retry_times);
    //             $product_details = $this->request->parse_json($product_response);
    //             $product = $this->parse_product_v3($product_details, $ignore_image);
    
    //         } catch (Exception $e){
    //             $this->logger->debug('Product V3 Endpoint Error: ' . $product_site_id . ' -> ' . $e->getMessage());
    //         }
    //     }
        
    //     if(is_null($product_response)){
            
    //         $endpoint_v2 = $product_endpoints->v2 . $product_site_id;

    //         try {
    //             $product_response = $this->request->request($endpoint_v2, 'GET', [], [], 300, $retry_times);
    //             $product_details = $this->request->parse_json($product_response);
    //             $product = $this->parse_product_v2($product_details, $ignore_image);
    //         } catch(Exception $e){
    //             $this->logger->error('Product Not Found On Either Endpoints: ' . $e->getMessage());
    //             // throw new Exception('Product Not Found On Either Endpoints');
    //             return null;
    //         }

    //     }
        
    //     return $product;
    // }


    // private function parse_product_v2($product_details, $ignore_image=false): ?ProductModel {
    //     $product = new ProductModel($this->database);

    //     // $product->name = $product_details->title;
    //     $product->name = $this->create_name( $product_details->title, $product_details->brand);

    //     $product->available = 1;

    //     $this->set_description($product, $product_details->longDescription);
    //     $product->brand = $product_details->brand;

    //     $variant = $product_details->variants[0];
    //     $price_details = $variant->offers[0];
    //     $inventory = $variant->specifications;

    //     $product->price = $price_details->price;

    //     if(!is_null($price_details->salePrice)){
    //         $product->is_on_sale = true;
    //         $product->price = $price_details->salePrice;
    //         $product->old_price = $price_details->wasPrice;
    //     }

    //     $product->store_type_id = $this->store_type_id;
    //     $product->site_product_id = $product_details->productId;

    //     $product->images = [];
    //     $product->ingredients = [];

    //     if(!$ignore_image){
    //         foreach($price_details->media->images as $index => $image_url){
    //             if($index == 0){
    //                 $saved_image_url =  $this->create_image($product->site_product_id, $image_url, 'large');

    //                 if(!is_null($saved_image_url)){
    //                     $product->small_image = $saved_image_url;
    //                     $product->large_image = $saved_image_url;
    //                 }
    //             } else {
    //                 $image = new ProductImageModel($this->database);
    
    //                 $image_name = $this->create_image($product->site_product_id . '_' . $index, $image_url, 'large');
    
    //                 if(!is_null($image_name)){
    //                     $image->name = $image_name;
    //                     $image->size = 'large'; 
    //                     $product->images[] = $image;
    //                 }
    
    //             }
    //         }
    //     }

        
    //     $this->set_barcodes_v2($product, $inventory);
        
    //     $product->currency = $this->currency;

    //     $product->url = "https://www.realcanadiansuperstore.ca" . $product_details->uri;

    //     return $product;
    // }

    // public function set_barcodes_v2(&$product, $inventory){
    //     $barcodes_data = [
    //         'upc' => $inventory->upc,
    //         'ean' => $inventory->ean,
    //         'mpn' => $inventory->mpn,
    //         'isbn' => $inventory->isbn,
    //         'asin' => $inventory->asin
    //     ];

    //     $product->barcodes = [];
    //     foreach($barcodes_data as $type => $value){

    //         if(!is_null($value) && $value != ''){
    //             $barcode = new BarcodeModel($this->database);
    //             $barcode->type = $type;
    //             $barcode->value = $value;
    //             $barcode->store_type_id = $this->store_type_id;
    
    //             $product->barcodes[] = $barcode;
    //         }

    //     }
    // }


    // private function parse_product_v3($product_details, $ignore_image=false): ?ProductModel {
    //     $product = new ProductModel($this->database);

    //     // $product->name = $product_details->name;
    //     $product->name = $this->create_name( $product_details->name, $product_details->brand);
        
    //     $product->available = 1;
    //     $product->site_product_id = $product_details->code;
    //     $product->store_type_id = $this->store_type_id;
        
    //     $product->weight = $product_details->packageSize;
    //     $product->currency = $this->currency;
        
    //     $this->set_price_v3($product, $product_details);

    //     $this->set_product_group_v3($product, $product_details);

    //     $this->set_description($product, $product_details->description);

    //     $product->brand = $product_details->brand;
    //     $product->url = "https://www.realcanadiansuperstore.ca" . $product_details->link;

    //     $product->images = [];
    //     $product->categories = [];

    //     foreach($product_details->breadcrumbs ?? [] as $category_data){
    //         $product->categories[] = $category_data->categoryCode;
    //     }

    //     $product->ingredients = [];

    //     $ingredients_text = $product_details->ingredients;
    //     if(!is_null($ingredients_text)){
    //         $ingredients = preg_split('/,|\./', trim($ingredients_text));

    //         foreach($ingredients as $ingredient_name){
    //             $ingredient = new IngredientModel($this->database);
    //             $name = trim(ucwords(strtolower($ingredient_name)));

    //             if($name != ''){
    //                 $ingredient->name = trim(ucwords(strtolower($ingredient_name)));
    //                 $product->ingredients[] = $ingredient;
    //             }

    //         }
    //     }

    //     if(!$ignore_image){
    //         foreach($product_details->imageAssets as $index => $image_asset){
                
    //             $image_url = $image_asset->smallUrl ?? null;

    //             if($index == 0){
                    
    //                 if(!is_null($image_url)){
    //                     $saved_image_url = $this->create_image($product->site_product_id, $image_url, 'large');

    //                     if(!is_null($saved_image_url)){
    //                         $product->small_image = $saved_image_url;
    //                         $product->large_image = $saved_image_url;
    //                     }
    //                 }
    //             } else {

    //                 if(!is_null($image_url)){
    //                     $image = new ProductImageModel($this->database);
    
    //                     $saved_image_url = $this->create_image($product->site_product_id . '_' . $index, $image_url, 'large');
        
    //                     if(!is_null($saved_image_url)){
    //                         $image->name = $saved_image_url;
    //                         $image->size = 'large'; 
    //                         $product->images[] = $image;
    //                     }
    //                 }
    //             }
    //         }
    //     }

    //     if(!is_null($product_details->upcs)){
    //         throw new Exception('Non Empty UPC Found');
    //     }

    //     return $product;
    // }



    // private function set_product_group_v3($product, $product_details){
    //     $product_group = $product_details->breadcrumbs[3] ?? last($product_details->breadcrumbs);

    //     $site_product_group_id = $product_group->categoryCode;
    //     $product_group_name = $product_group->name;

    //     $product->product_group = (object)['id' => $site_product_group_id, 'name' => $product_group_name];
    // }

    // private function set_price_v3($product, $product_details){

    //     $price_details = $product_details->prices;

    //     $breadcrumbs = $product_details->breadcrumbs;
    //     $site_category_id = end($breadcrumbs)->categoryCode;

    //     if(is_null($site_category_id)){
    //         throw new Exception('Site Category ID Not Found');
    //     }

    //     $product->site_category_id = $site_category_id;

    //     $product->price = $price_details->price->value;

    //     $deal = $product_details->badges->dealBadge;

    //     if(!is_null($price_details->wasPrice)){
    //         $ends_at = $deal->expiryDate;

    //         $product->is_on_sale = true;
    //         $product->sale_ends_at = date('Y-m-d H:i:s', strtotime($ends_at));
    //         $product->old_price = $price_details->wasPrice->value;

    //     } else if(!is_null($product_details->badges->dealBadge)){
    //         if(is_null($this->promotions)){
    //             $this->promotions = new Promotions($this->config,$this->logger,$this->database,$this->remember);
    //         }

    //         $product->promotion = $this->promotions->parse_promotion_v3($deal, $site_category_id);
    //     }

    // }


    // private function create_image($name, $url, $size): ?string {
    //     return $this->image->save($name, $url, $size, "products", $this->store_name);
    // }

    // private function set_description(ProductModel $product, $description) {
    //     preg_match('/(.+)Features(.+)Dimensions(.+)/i', $description, $matches);

    //     if($matches){
    //         $start_description = $matches[1];
    //         $features = $matches[2];
    //         $dimensions = $matches[3];
            
    //         $product->features = $this->create_description($features);
    //         $product->dimensions = $this->create_description($dimensions);
    //         $product->description = str_replace('..','.', preg_replace('/<\/*\w+>\s*<\/*\w+>/',".\n\n", $start_description));
    //     } else {
    //         $product->description = str_replace('..','.', preg_replace('/<\/*\w+>\s*<\/*\w+>/',".\n\n", $description));
    //     }

    //     $product->description = trim($product->description) == '' ? NULL : $product->description;
    // }

    // private function create_description($description){
    //     $description_list = $this->seperate_description($description) ?? [$description];

    //     $description_output = [];

    //     foreach($description_list as $description_item){
    //         $item = $this->clean_description_name($description_item);

    //         if($item != ''){
    //             $description_output[] = $item;
    //         }
    //     }
        
    //     return json_encode($description_output);
    // }

    // private function create_name($name, $brand = null){

    //     $new_name = trim($name);

    //     // Add Brand to name if brand not null
    //     if(!is_null($brand) && strtolower($brand) != 'no name'){

    //         // If any part of brand found in name, then exclde
    //         $brand_regex = str_replace(' ', '|', $brand);

    //         preg_match("/$brand_regex/i", $name, $brand_matches);

    //         if($brand_matches){
    //             $this->logger->debug("Brand($brand) Found In Product Name: $name");
    //         } else {
    //             $this->logger->debug("Brand($brand) Not Found In Product Name: $name");
    //             $new_name = trim(trim($brand) .' '. trim($name));
    //         }

    //         if(strlen($new_name) > 255){
    //             $new_name = substr($new_name, 0, 248) . '...';
    //         }

    //     }

    //     return $new_name;
    // }

    // private function seperate_description($description){
    //     $description = preg_replace('/<\/*\S+>/',"\n", $description);
    //     return explode("\n", $description);
    // }

    // private function clean_description_name($description){
    //     $description = preg_replace('/^-\s+|^:|Specifications\//','', $description);
    //     $description = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "", $description);
    //     $description = $this->sanitize->sanitize_field($description);
    //     return strip_tags($description);
    // }
    
}

?>