<?php

namespace Supermarkets\Canadian_Superstore\Groceries\Products;

use Exception;
use Models\Product\IngredientModel;
use Models\Product\ProductImageModel;
use Models\Product\ProductModel;
use Supermarkets\Canadian_Superstore\Groceries\Promotions\Promotions;

class ProductV3 extends Products {

    private $promotions;

    public function parse_product($product_details, $ignore_image=false): ?ProductModel {
        $product = new ProductModel($this->database);

        $product->name = $this->product_detail_service->create_name( $product_details->name, $product_details->brand);
        
        $product->available = 1;
        $product->site_product_id = $product_details->code;
        $product->store_type_id = $this->store_type_id;
        
        $product->weight = $product_details->packageSize;
        $product->currency = $this->currency;
        
        $this->set_price_v3($product, $product_details);

        $this->set_product_group_v3($product, $product_details);

        $this->product_detail_service->set_description($product, $product_details->description);

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
                
                $image_url = $image_asset->smallUrl ?? null;

                if($index == 0){
                    
                    if(!is_null($image_url)){
                        $saved_image_url = $this->product_detail_service->create_image($product->site_product_id, $image_url, 'large');

                        if(!is_null($saved_image_url)){
                            $product->small_image = $saved_image_url;
                            $product->large_image = $saved_image_url;
                        }
                    }
                } else {

                    if(!is_null($image_url)){
                        $image = new ProductImageModel($this->database);
    
                        $saved_image_url = $this->product_detail_service->create_image($product->site_product_id . '_' . $index, $image_url, 'large');
        
                        if(!is_null($saved_image_url)){
                            $image->name = $saved_image_url;
                            $image->size = 'large'; 
                            $product->images[] = $image;
                        }
                    }
                }
            }
        }

        if(!is_null($product_details->upcs)){
            throw new Exception('Non Empty UPC Found');
        }

        return $product;
    }

    private function set_product_group_v3($product, $product_details){
        $product_group = $product_details->breadcrumbs[3] ?? last($product_details->breadcrumbs);

        $site_product_group_id = $product_group->categoryCode;
        $product_group_name = $product_group->name;

        $product->product_group = (object)['id' => $site_product_group_id, 'name' => $product_group_name];
    }

    private function set_price_v3($product, $product_details){

        $price_details = $product_details->prices;

        $breadcrumbs = $product_details->breadcrumbs;
        $site_category_id = end($breadcrumbs)->categoryCode;

        if(is_null($site_category_id)){
            throw new Exception('Site Category ID Not Found');
        }

        $product->site_category_id = $site_category_id;

        $product->price = $price_details->price->value;

        $deal = $product_details->badges->dealBadge;

        if(!is_null($price_details->wasPrice)){
            $ends_at = $deal->expiryDate;

            $product->is_on_sale = true;
            $product->sale_ends_at = date('Y-m-d H:i:s', strtotime($ends_at));
            $product->old_price = $price_details->wasPrice->value;

        } else if(!is_null($product_details->badges->dealBadge)){
            if(is_null($this->promotions)){
                $this->promotions = new Promotions($this->config,$this->logger,$this->database,$this->remember);
            }

            $product->promotion = $this->promotions->parse_promotion($deal, $site_category_id);
        }

    }
    
}

?>