<?php

namespace Stores\Asda;

use Shared\Request;
use Shared\Requests;
use Exception;
use Models\Product\PriceModel;
use Models\Product\PromotionModel;

class AsdaPromotions extends Asda {

    function __construct($config,$logger,$database,$remember)
    {
        parent::__construct($config,$logger,$database,$remember);
    }


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

            if(!$promotion_results){
                $this->logger->notice("New Promotion Found: $promotion_name($promotion_site_id)");
                // $promotion->name = $promotion_name;
                $promotion->name = $this->promotion_name($promotion_site_id);
                $promotion->store_type_id = $this->config->get('asda.store_type_id');
                $promotion->site_promotion_id = $promotion_site_id;
                $promotion->url = "https://groceries.asda.com/promotion/$promotion_name/$promotion_site_id";
                $promotion_insert_id = $promotion->save();
            } else {
                $this->logger->notice("Promotion Found In Database: $promotion_name($promotion_site_id)");
                $promotion_insert_id = $promotion_results->id;
            }
            
        } else {
            throw new Exception('Product Not Part Of Promotion.');
        }


        $price_details->promotion_id = $promotion_insert_id;

    }

    public function product_item_price($product_data){
        return $this->sanitize->removeCurrency($product_data->price->price_info->price);
    }

    public function promotion_name($promotion_site_id){
        $promotion_url = $this->endpoints->promotions . $promotion_site_id;

        if($this->env == 'dev'){
            $promotion_response = file_get_contents(__DIR__."/../../Data/Asda/Promotion.json");
        } else {
            $promotion_response = $this->request->request($promotion_url);
        }

        $promotion_info = $this->request->parse_json($promotion_response);

        $name = $promotion_info->contents[0]->mainContent[1]->contents[0]->records[0]->attributes->{'sku.promoDisplayName'}[0];

        return ucwords(strtolower($name));
    }

}

?>