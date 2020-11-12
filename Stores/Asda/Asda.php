<?php

namespace Stores\Asda;

use Exception;
use Models\Store\StoreTypeModel;
use Shared\Image;
use Shared\Requests;
use Shared\Sanitize;
class Asda {

    public 
        $logger,
        $request,
        $config,
        $database,
        $endpoints,
        $env,
        $sanitize,
        $store_type_id,
        $user_id,
        $city,
        $exclusions,
        $remember,
        $image;
    
    function __construct($config,$logger,$database,$remember){
        $this->request = new Requests($config,$logger);
        $this->logger  = $logger;
        $this->config  = $config;
        $this->database = $database;

        $asda_conf = $this->config->get('asda');

        $this->endpoints = $this->config->get('endpoints')->asda;
        $this->env = $this->config->get('env');

        $this->sanitize = new Sanitize();

        $this->store_type_id = $asda_conf->store_type_id;
        $this->user_id = $asda_conf->user_id;
        $this->city = $config->get('city');

        $this->exclusions = $config->get('exclusions');

        $this->remember = $remember;

        $this->image = new Image($config,$logger,$this->request);

    }

    public function store_type(){
        $store_type = new StoreTypeModel($this->database);

        $store = $store_type->where(['id' => $this->store_type_id ])->get()[0] ?? null;

        if(!$store){
            $store_type->id = $this->store_type_id;
            $store_type->name = 'Asda';
            $store_type->user_id = 1;
            $store_type->large_logo = $this->image->save('asda','https://dynl.mktgcdn.com/p/uxpSIwyZRALdFsUMpGERiKVVeUVlEaMMTBvKbuOZB-E/150x150.png','large','logos');
            $store_type->small_logo =  $this->image->save('asda','https://dynl.mktgcdn.com/p/uxpSIwyZRALdFsUMpGERiKVVeUVlEaMMTBvKbuOZB-E/150x150.png','small','logos');
            $store_type->save();  
        }
 
    }

    public function recommended(){
        $recommended = new AsdaRecommended($this->config,$this->logger,$this->database,$this->remember);
        $recommended->all_recommended_products();
    }

    public function groceries(){
        $groceries = new AsdaGroceries($this->config,$this->logger,$this->database,$this->remember);
        $groceries->groceries();
    }

    public function promotions(){
        $promotions = new AsdaPromotions($this->config,$this->logger,$this->database,$this->remember);
        $promotions->promotions();
    }

    public function stores(){
        $stores = new AsdaStores($this->config,$this->logger,$this->database,$this->remember);
        $stores->stores();
    }

    public function reviews(){
        $reviews = new AsdaReviews($this->config,$this->logger,$this->database,$this->remember);
        $reviews->reviews();
    }


    //Shared Functionality
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
        $exclusions_list = join('|', array_merge($this->exclusions->products->exclude,$this->exclusions->categories->exclude) );
        preg_match("/$exclusions_list/i",$product_name,$product_matches);

        if($product_matches){
            //Haram Product Found
            return true;
        } else {
            return false;
        }   
    }

    public function product_possible_haram($product_name){
        $exclusions_list = join('|', $this->exclusions->products->check );
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
            $exclusions_list = join('|',$this->exclusions->ingredients->exclude);
            preg_match("/$exclusions_list/i",$ingredient_name,$haram_matches);

            if($haram_matches){
                return true;
            }
        }

        return false;

    }

}

?>