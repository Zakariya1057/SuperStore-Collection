<?php

namespace Collection\Supermarkets\Canadian_Superstore;

use Exception;
use Models\Store\StoreTypeModel;

use Collection\Supermarkets\Canadian_Superstore\Groceries\Groceries;
use Collection\Supermarkets\Canadian_Superstore\Groceries\Recommended\Recommended;
use Collection\Supermarkets\Canadian_Superstore\Stores\Stores;
use Monolog\Logger;
use Services\ConfigService;
use Services\DatabaseService;
use Services\ImageService;
use Services\LoggerService;
use Services\RememberService;
use Services\RequestService;
use Services\SanitizeService;

class CanadianSuperstore {

    public 
        $logger,

        $request_service,
        $config_service,

        $remember_service,
        $currency_service,

        $database_service,
        $sanitize_service,

        $endpoints,
        $env,

        $store_type_id,
        $user_id,
        $city,

        $image;
    
    function __construct(ConfigService $config_service, Logger $logger, DatabaseService $database_service, RememberService $remember_service=null){
        $this->request_service = new RequestService($config_service, $logger);
        $this->logger = $logger;
        $this->config_service = $config_service;
        $this->database_service = $database_service;

        $canadian_superstore = $this->config_service->get('stores.real_canadian_superstore');

        $this->endpoints = $this->config_service->get('endpoints.real_canadian_superstore');
        $this->env = $this->config_service->get('env');

        $this->sanitize_service = new SanitizeService();

        $this->store_name = $canadian_superstore->name;
        $this->store_type_id = $canadian_superstore->store_type_id;
        $this->user_id = $canadian_superstore->user_id;

        $this->city = $config_service->get('city');
        $this->remember_service = $remember_service;

        $this->image_service = new ImageService($config_service, $logger, $this->request_service);

        $this->currency_service = $canadian_superstore->currency;

    }


    public function store_type(){
        $store_type = new StoreTypeModel($this->database_service);

        $store = $store_type->where(['id' => $this->store_type_id ])->get()[0] ?? null;

        if(is_null($store)){
            $store_type->id = $this->store_type_id;
            $store_type->name = $this->store_name;
            $store_type->user_id = $this->user_id;
            $store_type->currency = $this->currency_service;
            $store_type->large_logo = $this->image_service->save('logo','https://dynl.mktgcdn.com/p/uxpSIwyZRALdFsUMpGERiKVVeUVlEaMMTBvKbuOZB-E/150x150.png','large','logos',$this->store_name);
            $store_type->small_logo =  $this->image_service->save('logo','https://dynl.mktgcdn.com/p/uxpSIwyZRALdFsUMpGERiKVVeUVlEaMMTBvKbuOZB-E/150x150.png','small','logos',$this->store_name);
            $store_type->save();  
        }
 
    }

    public function groceries(){
        $groceries = new Groceries($this->config_service,$this->logger,$this->database_service,$this->remember_service);
        $groceries->create_groceries();
    }

    public function recommended(){
        $groceries = new Recommended($this->config_service,$this->logger,$this->database_service,$this->remember_service);
        $groceries->create_recommended();
    }

    public function stores(){
        $groceries = new Stores($this->config_service,$this->logger,$this->database_service,$this->remember_service);
        $groceries->create_stores();
    }
}

?>