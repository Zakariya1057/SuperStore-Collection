<?php

namespace Supermarkets\Asda;

use Exception;
use Illuminate\Session\Store;
use Models\Store\StoreTypeModel;
use Monolog\Logger;
use Services\Config;
use Services\Database;
use Services\Image;
use Services\Remember;
use Services\Requests;
use Services\Sanitize;
use Supermarkets\Asda\Groceries\Groceries;
use Supermarkets\Asda\Groceries\Products\Promotions;
use Supermarkets\Asda\Groceries\Products\Recommended;
use Supermarkets\Asda\Groceries\Products\Reviews;
use Supermarkets\Asda\Services\ExcludeService;
use Supermarkets\Asda\Stores\Stores;

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
        $image,
        $exclude_service;
    
    function __construct(Config $config, Logger $logger, Database $database, Remember $remember=null){
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
        $this->remember = $remember;

        $this->image = new Image($config,$logger,$this->request);

        $this->exclude_service = new ExcludeService( $config->get('exclusions') );

    }

    public function store_type(){
        $store_type = new StoreTypeModel($this->database);

        $store = $store_type->where(['id' => $this->store_type_id ])->get()[0] ?? null;

        if(is_null($store)){
            $store_type->id = $this->store_type_id;
            $store_type->name = 'Asda';
            $store_type->user_id = 1;
            $store_type->large_logo = $this->image->save('asda','https://dynl.mktgcdn.com/p/uxpSIwyZRALdFsUMpGERiKVVeUVlEaMMTBvKbuOZB-E/150x150.png','large','logos');
            $store_type->small_logo =  $this->image->save('asda','https://dynl.mktgcdn.com/p/uxpSIwyZRALdFsUMpGERiKVVeUVlEaMMTBvKbuOZB-E/150x150.png','small','logos');
            $store_type->save();  
        }
 
    }

    public function recommended(){
        $recommended = new Recommended($this->config,$this->logger,$this->database,$this->remember);
        $recommended->all_recommended_products();
    }

    public function groceries(){
        $groceries = new Groceries($this->config,$this->logger,$this->database,$this->remember);
        $groceries->groceries();
    }

    public function promotions(){
        $promotions = new Promotions($this->config,$this->logger,$this->database,$this->remember);
        $promotions->promotions();
    }

    public function stores(){
        $stores = new Stores($this->config,$this->logger,$this->database,$this->remember);
        $stores->stores();
    }

    public function reviews(){
        $reviews = new Reviews($this->config,$this->logger,$this->database,$this->remember);
        $reviews->reviews();
    }

}

?>