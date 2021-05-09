<?php

namespace Supermarkets\Asda\Groceries\Products;

use Exception;
use Models\Product\PriceModel;
use Models\Product\PromotionModel;
use Supermarkets\Asda\Asda;

class Promotions extends Asda {

    public function product_prices($product_data){

        $promotion_info = $product_data->promotion_info[0];

        $price_details = new PriceModel();
        
        $price_details->price = $this->product_item_price($product_data);

        if($product_data->price->is_on_sale){
            //Product On Sale
            $this->logger->debug('Product Is On Sale');
            $this->product_on_sale($product_data,$price_details);
        } elseif(!is_null($promotion_info->rollback) ){
             //Product RollBack
            $this->logger->debug('Product Is Rollback');
            $this->product_rollback($promotion_info,$price_details);
        } elseif(!is_null($promotion_info->linksave)){
            //2 For £10.
            $this->logger->debug('Product Is Promotion Group');
            $this->product_promotion($promotion_info,$price_details);
        } else {
            //No Product Promotion
        }

        return $price_details;
    }

    public function product_on_sale($product_data,$price_details){
        //Get Prices For Product On Sale

        $price_data = $product_data->price;

        if($price_data->is_on_sale == true){
            $this->logger->debug('Product Sale Found');
            $price_details->old_price = $price_details->price;
            $price_details->price = $this->sanitize->removeCurrency($product_data->price->price_info->sale_price);
            $price_details->is_on_sale = true;
        } else {
            throw new Exception('Product Not On Sale');
        }

    }

    public function product_rollback($promotion_info,$price_details){
        //Get Prices For Rollback Product
        $rollback = $promotion_info->rollback;

        if(!is_null($rollback) ){
            $this->logger->debug('RollBack Product Found');
            $price_details->old_price = $this->sanitize->removeCurrency( $rollback->was_price );
        } else {
            throw new Exception('Not A RollBack Product');
        }

    }

    public function product_promotion($promotion_info,$price_details){

        $promotion_details = $promotion_info->linksave;
        $promotion_site_id = $promotion_details->promo_id;

        $promotion = new PromotionModel($this->database);
        
        if(!is_null($promotion_details)){
            $this->logger->debug('Product Promotion Starting');

            $promotion_results = $promotion->where(['site_promotion_id' => $promotion_site_id])->get()[0] ?? null;
            $promotion_name = $promotion_details->promo_detail;
            $promotion_site_id = $promotion_details->promo_id;

            try {
                $promotion_info = $this->parse_promotion($promotion_name);
            } catch(Exception $e) {
                $promotion_info = $this->promotion_info($promotion_site_id);
            }

            $promotion->name = $promotion_info->name;
            $promotion->quantity = $promotion_info->quantity;
            $promotion->price = $promotion_info->price;
            $promotion->for_quantity = $promotion_info->for_quantity;

            $promotion->site_promotion_id = $promotion_site_id;

            $promotion->store_type_id = $this->config->get('stores.asda.store_type_id');
           
            $promotion->url = "https://groceries.asda.com/promotion/$promotion_name/$promotion_site_id";
            
            if(is_null($promotion_results)){
                $this->logger->notice("New Promotion Found: $promotion_name($promotion_site_id)");
            } else {
                $this->logger->notice("Promotion Found In Database: $promotion_name($promotion_site_id)");
                $promotion->id = $promotion_results->id;
            }

        } else {
            throw new Exception('Product Not Part Of Promotion.');
        }

        $price_details->promotion = $promotion;

    }

    public function product_item_price($product_data){
        return $this->sanitize->removeCurrency($product_data->price->price_info->price);
    }

    public function promotion_info($promotion_site_id){

        if($this->env == 'dev'){
            $promotion_response = file_get_contents(__DIR__."/../../data/Asda/New_Promotion.json");
            $promotion_info = $this->request->parse_json($promotion_response);
        } else {
            $promotion_info = $this->request_details('promotion', $promotion_site_id);
        }

        foreach($promotion_info->zones as $promotion_item){
            $promotion_details = $promotion_item->configs;

            if(property_exists($promotion_details, 'promo_display_name')){
                $name = $promotion_details->promo_display_name;
                $name = ucwords(strtolower($name));
                return $this->parse_promotion($name);
            }
        }

        return null;
    }

    private function parse_promotion($promotion_name){

        $value = html_entity_decode($promotion_name, ENT_QUOTES);
        preg_match('/(\d+).+£(\d+\.*\d*)$/',$value,$price_promotion_matches);

        $quantity = $price = $for_quantity = null;

        if($price_promotion_matches){
            $quantity = (int)$price_promotion_matches[1];
            $price = (float)$price_promotion_matches[2];
        }

        preg_match('/(\d+).+\s(\d+)$/',$promotion_name,$quantity_promotion_matches);
        if($quantity_promotion_matches){
            $quantity = (int)$quantity_promotion_matches[1];
            $for_quantity = (int)$quantity_promotion_matches[2];
        }

        if(!$quantity_promotion_matches && !$price_promotion_matches){
            // Wine buy 6 
            preg_match('/Wine buy 6|1 Main 1 Side 1 Dessert 1 Drink|2 Pizzas, 1 Side, 1 Drink|2 Pizzas 1 Drink - £5 Pizza Deal/i',$value,$ignore_promotions);

            if(!$ignore_promotions){
                throw new Exception('Unknown Promotion Type Encountered: ' . $promotion_name);
            }
        }

        return (object)['name' => $promotion_name, 'quantity' => $quantity, 'price' => $price, 'for_quantity' => $for_quantity];
    }

}

?>