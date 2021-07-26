<?php

namespace Collection\Loblaws\Services;

use Exception;
use Collection\Loblaws\Loblaws;
use Models\Product\PromotionModel;

class PromotionService extends Loblaws {

    private $promotion_model;

    private function setupPromotionModel(){
        if(is_null($this->promotion_model)){
            $this->promotion_model = new PromotionModel($this->database_service);
        }
    }

    public function parse_promotion($promotion_details, int $region_id, int $supermarket_chain_id, $site_category_id, $site_category_name){

        $this->setupPromotionModel();
        
        $promotion_name = $promotion_details->text;
        $promotion_expires = $promotion_details->expiryDate ?? null;
        
        // $5.98 LIMIT 4
        preg_match('/(\d+\.*\d*) LIMIT (\d+)/i', $promotion_name, $promotion_max_matches);

        // $2.68 MIN 3
        preg_match('/(\d+\.*\d*) MIN (\d+)/i', $promotion_name, $promotion_min_matches);

        // 2 FOR $12.00
        preg_match('/(\d+) FOR \$(\d+\.*\d*)/i', $promotion_name, $promotion_for_matches);

        $price = $minimum = $maximum = $quantity = null;

        if($promotion_min_matches){
            $price = $promotion_min_matches[1];
            $minimum = $promotion_min_matches[2];
        } else if($promotion_for_matches){
            $price = $promotion_for_matches[2];
            $quantity = $promotion_for_matches[1];
        } else if($promotion_max_matches){
            $price = $promotion_max_matches[1];
            $maximum = $promotion_max_matches[2];
        }

        if(is_null($promotion_for_matches) && is_null($promotion_min_matches)){
            throw new Exception('Unknown Promotion Type Encountered: '. $promotion_name);
        }

        $promotion = clone $this->promotion_model;
        
        $promotion_results = null;

        $where = [
            'supermarket_chain_id' => $supermarket_chain_id,
            'region_id' => $region_id,
            'name' => $promotion_name,
            'site_category_id' => $site_category_id,
            'expires' => is_null($promotion_expires) ? NULL : 1
        ];

        if(!is_null($promotion_expires)){
            $where['ends_at'] = date('Y-m-d H:i:s', strtotime($promotion_expires));
        }

        $promotion_results = $promotion->where($where)->cast(PromotionModel::class)->first();

        if(is_null($promotion_results)){
            $this->logger->debug('Creating New Promotion Not Found In Database');

            $promotion->supermarket_chain_id = $supermarket_chain_id;

            $promotion->title = $site_category_name;
            $promotion->region_id = $region_id;

            $promotion->name = $promotion_name;
            $promotion->price = $price;
            $promotion->minimum = $minimum;
            $promotion->maximum = $maximum;
            $promotion->quantity = $quantity;
            $promotion->site_category_id = $site_category_id;
    
            if(!is_null($promotion_expires)){
                $promotion->expires = 1;
                $promotion_expires = date('Y-m-d H:i:s', strtotime($promotion_expires));
            }
    
            $promotion->ends_at = $promotion_expires;
        } else {
            $this->logger->debug('Promotion Found In Database');
            $promotion = $promotion_results;
        }

        return $promotion;
    }
    
}

?>