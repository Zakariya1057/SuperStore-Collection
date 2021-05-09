<?php

namespace Collection\Supermarkets\Canadian_Superstore\Services;

use Models\Product\ProductModel;
use Collection\Supermarkets\Canadian_Superstore\CanadianSuperstore;

class ProductDetailService extends CanadianSuperstore {

    public function create_name($name, $brand = null){

        $new_name = trim($name);

        // Add Brand to name if brand not null
        if(!is_null($brand) && strtolower($brand) != 'no name'){

            // If any part of brand found in name, then exclde
            $brand_regex = str_replace(' ', '|', $brand);

            preg_match("/$brand_regex/i", $name, $brand_matches);

            if($brand_matches){
                $this->logger->debug("Brand($brand) Found In Product Name: $name");
            } else {
                $this->logger->debug("Brand($brand) Not Found In Product Name: $name");
                $new_name = trim(trim($brand) .' '. trim($name));
            }

            if(strlen($new_name) > 255){
                $new_name = substr($new_name, 0, 248) . '...';
            }

        }

        return $new_name;
    }
    
    public function set_description(ProductModel $product, $description) {
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

        $product->description = trim($product->description) == '' ? NULL : $product->description;
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

    public function seperate_description($description){
        $description = preg_replace('/<\/*\S+>/',"\n", $description);
        return explode("\n", $description);
    }

    public function clean_description_name($description){
        $description = preg_replace('/^-\s+|^:|Specifications\//','', $description);
        $description = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "", $description);
        $description = $this->sanitize->sanitize_field($description);
        return strip_tags($description);
    }

    public function create_image($name, $url, $size): ?string {
        return $this->image->save($name, $url, $size, "products", $this->store_name);
    }
}

?>