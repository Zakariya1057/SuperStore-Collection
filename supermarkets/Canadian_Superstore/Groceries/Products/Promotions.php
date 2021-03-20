<?php

namespace Supermarkets\Canadian_Superstore\Groceries\Products;

use Exception;
use Models\Product\PromotionModel;
use Supermarkets\Canadian_Superstore\CanadianSuperstore;

class Promotions extends CanadianSuperstore {

    public function parse_promotion_v3($promotion_details){
        $promotion_name = $promotion_details->text;
        $promotion_expires = $promotion_details->expiryDate ?? null;
        
        // $2.68 MIN 3
        preg_match('/(\d+\.*\d*) MIN (\d+)/i', $promotion_name, $promotion_min_matches);

        // 2 FOR $12.00
        preg_match('/(\d+) FOR \$(\d+\.*\d*)/i', $promotion_name, $promotion_for_matches);

        $price = $minimum = $quantity = null;

        if($promotion_min_matches){
            $price = $promotion_min_matches[1];
            $minimum = $promotion_min_matches[2];
        }

        if($promotion_for_matches){
            $price = $promotion_for_matches[2];
            $quantity = $promotion_for_matches[1];
        }

        if(is_null($promotion_for_matches) && is_null($promotion_min_matches)){
            throw new Exception('Unknown Promotion Type Encountered: '. $promotion_name);
        }

        $promotion = new PromotionModel($this->database);
        $promotion->store_type_id = $this->store_type_id;

        $promotion->name = $promotion_name;
        $promotion->price = $price;
        $promotion->minimum = $minimum;
        $promotion->quantity = $quantity;

        if(!is_null($promotion_expires)){
            $promotion->expires = 1;
            $promotion_expires = date("Y-m-d H:i:s", strtotime($promotion_expires));
        }

        $promotion->ends_at = $promotion_expires;

        return $promotion->save();
    }

}

?>