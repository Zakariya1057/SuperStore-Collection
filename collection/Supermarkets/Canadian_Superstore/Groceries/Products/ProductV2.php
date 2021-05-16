<?php

namespace Collection\Supermarkets\Canadian_Superstore\Groceries\Products;

use Models\Product\ProductImageModel;
use Models\Product\ProductModel;
use Models\Product\BarcodeModel;

class ProductV2 extends Products {

    public function parse_product($product_details, $ignore_image=false): ?ProductModel {
        $product = new ProductModel($this->database);

        $product->availability_type = 'ship to home';

        $product->name = $this->product_detail_service->create_name( $product_details->title, $product_details->brand);

        $product->available = 1;

        $this->product_detail_service->set_description($product, $product_details->longDescription);
        $product->brand = $product_details->brand;

        $variant = $product_details->variants[0];
        $inventory = $variant->specifications;

        $price_details = $variant->offers[0];

        $this->set_prices($product, $price_details);

        $this->set_product_group($product, $product_details);

        $product->store_type_id = $this->store_type_id;
        $product->site_product_id = $product_details->productId;

        if(!$ignore_image){
            $this->set_images($product, $price_details->media->images);
        }
        
        $this->set_barcodes($product, $inventory);
        
        $product->currency = $this->currency;

        $product->url = 'https://www.realcanadiansuperstore.ca' . $product_details->uri;

        return $product;
    }

    private function set_prices(&$product, $price_details){
        
        $product->price = $price_details->price;

        if(!is_null($price_details->salePrice)){
            $product->is_on_sale = true;
            $product->price = $price_details->salePrice;
            $product->old_price = $price_details->wasPrice;
        }
    }

    private function set_images(&$product, $images){
        $product->images = [];
        $product->ingredients = [];

        foreach($images as $index => $image_url){
            if($index == 0){
                $saved_image_url = $this->product_detail_service->create_image($product->site_product_id, $image_url, 'large');

                if(!is_null($saved_image_url)){
                    $product->small_image = $saved_image_url;
                    $product->large_image = $saved_image_url;
                }
            } else {
                $image = new ProductImageModel($this->database);

                $image_name = $this->product_detail_service->create_image($product->site_product_id . '_' . $index, $image_url, 'large');

                if(!is_null($image_name)){
                    $image->name = $image_name;
                    $image->size = 'large'; 
                    $product->images[] = $image;
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

    private function set_barcodes(&$product, $inventory){
        $barcodes_data = [
            'upc' => $inventory->upc,
            'ean' => $inventory->ean,
            'mpn' => $inventory->mpn,
            'isbn' => $inventory->isbn,
            'asin' => $inventory->asin
        ];

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