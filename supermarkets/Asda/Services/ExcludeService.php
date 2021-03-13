<?php

namespace Supermarkets\Asda\Services;

class ExcludeService {

    public $exclusions = [];

    function __construct($exclusions){
        $this->exclusions = $exclusions;
    }

    public function include_category($category_name){
        $inclusion_list = join('|',$this->exclusions->categories->include);
        preg_match("/$inclusion_list/i",$category_name,$category_matches);
        return $category_matches ? true : false; 
    }

    public function exclude_category($category_name){
        $exclusions_list = join('|',$this->exclusions->categories->exclude );
        preg_match("/$exclusions_list/i",$category_name,$category_matches);

        if($category_matches){
            //Haram Category Found
            return true;
        } else {
            return false;
        }  
    }

    public function exclude_product($product_name){

        $exclusions_list = $this->match_whole( array_merge($this->exclusions->products->exclude,$this->exclusions->categories->exclude) );

        preg_match("/$exclusions_list/i",$product_name,$product_matches);

        if($product_matches){
            //Haram Product Found
            return true;
        } else {
            return false;
        }   
    }

    public function product_possible_haram($product_name){
        $exclusions_list = $this->match_whole( $this->exclusions->products->check );

        preg_match("/$exclusions_list/i",$product_name,$product_matches);
            
        if($product_matches){
            //Product May Be Haram
            return true;
        } else {
            return false;
        }   
    }

    public function haram_ingredients($ingredients){

        foreach($ingredients as $ingredient_name){
            $exclusions_list = $this->match_whole( $this->exclusions->ingredients->exclude );
            preg_match("/$exclusions_list/i",$ingredient_name,$haram_matches);

            if($haram_matches){
                return true;
            }
        }

        return false;

    }

    public function match_whole($exclusions){
        foreach($exclusions as $index => $item){
            $exclusions[$index] = "\b$item\b";
        }
        return join('|',  $exclusions);
    }
}

?>