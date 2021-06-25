<?php

namespace Collection\Supermarkets\Canadian_Superstore\Groceries\Products;

use Collection\Supermarkets\Canadian_Superstore\Services\PromotionService;

use Models\Product\IngredientModel;
use Models\Product\ProductImageModel;
use Models\Product\ProductModel;

use Exception;
use Models\Product\ChildNutritionModel;
use Models\Product\NutritionModel;
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

        $this->set_nutritions($product, $product_details->nutritionFacts);

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

    private function set_nutritions(ProductModel &$product, $nutrition_facts){
        $nutritions_list = [];

        if(count($nutrition_facts) > 0){
            $nutrition_facts = $nutrition_facts[0];

            // Product Nutritions Fact. Per Serving
            $top_nutritions = $nutrition_facts->topNutrition;
            $product->serving_size = $top_nutritions[0]->valueInGram;
            $product->household_serving_size =$top_nutritions[1]->valueInGram;
            unset($nutrition_facts->topNutrition);

            // Micronutritins Facts. Vitamins
            $micro_nutritions_data = $nutrition_facts->microNutrition;
            $micro_nutrition = new NutritionModel($this->database_service);
            $micro_nutrition->name = 'Micronutrients';
            $this->set_child_nutritions($micro_nutrition, $micro_nutritions_data);

            if(count($micro_nutrition->child_nutritions) > 0){
                $nutritions_list[] = $micro_nutrition;
            }
            
            unset($nutrition_facts->microNutrition);

            // Other Nutritions
            foreach($nutrition_facts as $name => $nutrition_data){
                if(is_null($nutrition_data) || $name == 'ingredients' || $this->empty_nutrition($nutrition_data)){
                    continue;
                }

                $name = $this->nutrition_name($name);

                $nutrition = new NutritionModel($this->database_service);
                $nutrition->name = $name;
                $nutrition->grams = $nutrition_data->valueInGram;
                $nutrition->percentage = $nutrition_data->valuePercent;

                $this->set_child_nutritions($nutrition, $nutrition_data->subNutrients ?? []);

                $nutritions_list[] = $nutrition;
            }
        }

        $product->nutritions = $nutritions_list;
    }

    private function set_child_nutritions(&$nutrition, $child_nutritions_data){
        $child_nutritions_list = [];

        foreach($child_nutritions_data as $child_nutrition_data){

            if( !$this->empty_nutrition($child_nutrition_data) ){
                $child_nutrition = new ChildNutritionModel($this->database_service);
                $child_nutrition->name = $this->nutrition_name($child_nutrition_data->code);
                $child_nutrition->grams = $child_nutrition_data->valueInGram;
                $child_nutrition->percentage = $child_nutrition_data->valuePercent;
    
                $child_nutritions_list[] = $child_nutrition;
            }

        }

        $nutrition->child_nutritions = $child_nutritions_list;
    }

    private function nutrition_name($name){
        preg_match_all('/((?:^|[A-Z])[a-z]*)/', $name, $name_matches);
        return ucwords( strtolower(join(' ', $name_matches[0]) ) );
    }

    private function empty_nutrition($nutrition){
        // Empty = No weight or percentage
        return $this->empty_percent($nutrition) && $this->empty_weight($nutrition);
    }

    private function empty_percent($nutrition){
        return is_null($nutrition->valuePercent) || $nutrition->valuePercent == '0 %';
    }

    private function empty_weight($nutrition){
        return is_null($nutrition->valueInGram)  || $nutrition->valueInGram == '0 g';
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