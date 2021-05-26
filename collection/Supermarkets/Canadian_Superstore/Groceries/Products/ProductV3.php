<?php

namespace Collection\Supermarkets\Canadian_Superstore\Groceries\Products;

use Collection\Supermarkets\Canadian_Superstore\Services\PromotionService;

use Models\Product\IngredientModel;
use Models\Product\ProductImageModel;
use Models\Product\ProductModel;

use Exception;
use Models\Product\ProductPriceModel;

class ProductV3 extends Products {

    private $promotion_service;
    private $product_model;

    private function setupClasses(){
        if(is_null($this->promotion_service)){
            $this->promotion_service = new PromotionService($this->config_service, $this->logger, $this->database_service);
        }

        if(is_null($this->product_model)){
            $this->product_model = new ProductModel($this->database_service);
        }
    }

    public function parse_product($product_details, $ignore_image=false): ?ProductModel {

        $this->setupClasses();

        $product = clone $this->product_model;

        $product->name = $this->product_service->create_name($product_details->name, $product_details->brand);
        
        $product->availability_type = 'in-store';

        $product->site_product_id = $product_details->code;
        $product->store_type_id = $this->store_type_id;
        
        $product->weight = $product_details->packageSize;
        $product->currency = $this->currency_service;
        
        if($product_details->breadcrumbs == []){
            $this->logger->error('No Product Category Found.');
            return null;
        }

        $this->set_product_group($product, $product_details);

        $product->prices = [];
        $product->promotions = [];
        
        $product->site_category_id = $product->product_group->id;

        // $this->set_prices($product, $product_details);

        $this->product_service->set_description($product, $product_details->description);

        $product->brand = $product_details->brand;
        $product->url = 'https://www.realcanadiansuperstore.ca' . $product_details->link;

        $this->set_categories($product, $product_details->breadcrumbs);

        $this->set_ingredients($product, $product_details->ingredients);

        if(!$ignore_image){
            $this->set_images($product, $product_details->imageAssets);
        }

        if(!is_null($product_details->upcs)){
            throw new Exception('Non Empty UPC Found');
        }

        return $product;
    }

    public function parse_prices($product_details, $product, $region_id){
        $product_price = new ProductPriceModel($this->database_service);
        $product_price->region_id = $region_id;

        $this->set_prices($product_price, $product, $product_details, $region_id);

        return $product_price;
    }


    private function set_categories(&$product, $breadcrumbs){
        $product->categories = [];

        foreach($breadcrumbs ?? [] as $category_data){
            $product->categories[] = $category_data->categoryCode;
        }
    }

    private function set_ingredients(&$product, $ingredients_text){
        $product->ingredients = [];

        if(!is_null($ingredients_text)){
            $ingredients = preg_split('/,|\./', trim($ingredients_text));

            foreach($ingredients as $ingredient_name){
                $ingredient = new IngredientModel($this->database_service);
                $name = trim(ucwords(strtolower($ingredient_name)));

                if($name != ''){
                    $ingredient->name = trim(ucwords(strtolower($ingredient_name)));
                    $product->ingredients[] = $ingredient;
                }

            }
        }
    }

    private function set_images(&$product, $images){

        $product->images = [];

        foreach($images as $index => $image_asset){
                
            $image_url = $image_asset->smallUrl ?? null;

            if($index == 0){
                
                if(!is_null($image_url)){
                    $saved_image_url = $this->product_service->create_image($product->site_product_id, $image_url, 'large');

                    if(!is_null($saved_image_url)){
                        $product->small_image = $saved_image_url;
                        $product->large_image = $saved_image_url;
                    }
                }
            } else {

                if(!is_null($image_url)){
                    $image = new ProductImageModel($this->database_service);

                    $saved_image_url = $this->product_service->create_image($product->site_product_id . '_' . $index, $image_url, 'large');
    
                    if(!is_null($saved_image_url)){
                        $image->name = $saved_image_url;
                        $image->size = 'large'; 
                        $product->images[] = $image;
                    }
                }
            }
        }
    }

    private function set_product_group($product, $product_details){
        $product_group = $product_details->breadcrumbs[3] ?? last($product_details->breadcrumbs);

        $site_product_group_id = $product_group->categoryCode;
        $product_group_name = $product_group->name;

        $product->product_group = (object)['id' => $site_product_group_id, 'name' => $product_group_name];
    }

    private function set_prices($product_price, $product, $product_details, $region_id){

        $price_details = $product_details->prices;

        $site_category_id = $product->product_group->id;
        $site_category_name = $product->product_group->name;

        if(is_null($site_category_id)){
            throw new Exception('Site Category ID Not Found');
        }

        $product_price->price = $price_details->price->value;

        $deal = $product_details->badges->dealBadge;

        $product_price->promotion = null;
        
        if(!is_null($price_details->wasPrice)){
            $ends_at = $deal->expiryDate;

            $product_price->is_on_sale = true;
            $product_price->sale_ends_at = date('Y-m-d H:i:s', strtotime($ends_at));
            $product_price->old_price = $price_details->wasPrice->value;

        } else if(!is_null($product_details->badges->dealBadge)){
            $product_price->promotion = $this->promotion_service->parse_promotion($deal, $region_id, $site_category_id, $site_category_name);
        }

    }
    
}

?>