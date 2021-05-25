<?php

namespace Collection\Supermarkets\Asda;

use Exception;
use Models\Store\StoreTypeModel;
use Monolog\LoggerService;
use Services\ConfigService;
use Services\DatabaseService;
use Services\ImageService;
use Services\RememberService;
use Services\RequestService;
use Services\SanitizeService;
use Collection\Supermarkets\Asda\Groceries\Groceries;
use Collection\Supermarkets\Asda\Groceries\Recommended\Recommended;
use Collection\Supermarkets\Asda\Groceries\Reviews\Reviews;
use Collection\Supermarkets\Asda\Services\CategoryService;
use Collection\Supermarkets\Asda\Stores\Stores;
use Monolog\Logger;

class Asda {

    public 
        $endpoints,
        $env,
        
        $store_type_id,
        $user_id,
        $store_name,
        $store_country,
        $city,
        
        $logger,

        $sanitize_service,
        $request_service,
        $config_service,
        $database_service,
        $currency_service,
        $remember_service,
        $image_service;

    public $category_service;
    
    function __construct(ConfigService $config_service, Logger $logger, DatabaseService $database_service, RememberService $remember_service=null){
        $this->request_service = new RequestService($config_service, $logger);
        
        $this->logger = $logger;
        $this->config_service = $config_service;
        $this->database_service = $database_service;

        $asda_conf = $this->config_service->get('stores.asda');

        $this->endpoints = $this->config_service->get('endpoints')->asda;
        $this->env = $this->config_service->get('env');

        $this->sanitize_service = new SanitizeService();

        $this->store_type_id = $asda_conf->store_type_id;
        $this->user_id = $asda_conf->user_id;
        $this->store_name = $asda_conf->name;

        $this->city = $config_service->get('city');
        $this->remember_service = $remember_service;

        $this->image_service = new ImageService($config_service, $logger, $this->request_service);

        $this->currency_service = $asda_conf->currency;
        $this->store_country = $asda_conf->country;

        $this->category_service = new CategoryService($this->config_service,$this->logger);
    }

    public function store_type(){
        $store_type = new StoreTypeModel($this->database_service);

        $store = $store_type->where(['id' => $this->store_type_id ])->first();

        if(is_null($store)){
            $store_type->id = $this->store_type_id;
            $store_type->name = $this->store_name;
            $store_type->user_id = $this->user_id;
            $store_type->currency = $this->currency_service;
            $store_type->large_logo = $this->image_service->save('Asda','https://dynl.mktgcdn.com/p/uxpSIwyZRALdFsUMpGERiKVVeUVlEaMMTBvKbuOZB-E/150x150.png','large','logos');
            $store_type->small_logo =  $this->image_service->save('Asda','https://dynl.mktgcdn.com/p/uxpSIwyZRALdFsUMpGERiKVVeUVlEaMMTBvKbuOZB-E/150x150.png','small','logos');
            $store_type->save();  
        }
 
    }

    public function recommended(){
        $recommended = new Recommended($this->config_service,$this->logger,$this->database_service,$this->remember_service);
        $recommended->all_recommended_products();
    }

    public function groceries(){
        $groceries = new Groceries($this->config_service,$this->logger,$this->database_service,$this->remember_service);
        $groceries->groceries();
    }

    public function stores(){
        $stores = new Stores($this->config_service,$this->logger,$this->database_service,$this->remember_service);
        $stores->stores();
    }

    public function reviews(){
        $reviews = new Reviews($this->config_service,$this->logger,$this->database_service,$this->remember_service);
        $reviews->reviews();
    }

}

?>