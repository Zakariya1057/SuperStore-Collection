<?php

namespace Supermarkets\Canadian_Superstore\Groceries\Products;

use Exception;
use Models\Data\Canadian_SuperStore\ProductDataModel;
use Models\Product\IngredientModel;
use Models\Product\ProductModel;
use Supermarkets\Canadian_Superstore\CanadianSuperstore;

class Products extends CanadianSuperstore {

    public function create_product($product_data, $request_type = 'v2'){

        $product = new ProductModel($this->database);

        if($request_type == 'v2'){
            $product = $this->parse_product_v2($product_data);
        } else {
            $product = $this->parse_product_v3($product_data);
        }

    
        if(!is_null($product)){

            $product_results = $product->where(['site_product_id' => $product->site_product_id])->get()[0] ?? null;

            if(is_null($product_results)){
                $this->logger->debug('New Product Found. Storing In Database: ');
                $product_id = $product->save();
                $this->create_ingredients($product_id, $product_data);
            } else {
                $this->logger->debug('Duplicate Product Found. Ignoring');
            }

        }

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
            $this->logger->debug('Product V3 Endpoint Error: ' . $product_site_id);
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
        $product->description = $product_details->longDescription;
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

        $product->description = $product_details->description;
        $product->brand = $product_details->brand;
        $product->url = "https://www.realcanadiansuperstore.ca" . $product_details->link;

        return $product;
    }

}

?>