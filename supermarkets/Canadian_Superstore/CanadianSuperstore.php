<?php

namespace Supermarkets\Canadian_Superstore;

use Exception;
use Models\Store\StoreTypeModel;
use Monolog\Logger;
use Services\Config;
use Services\Database;
use Services\Image;
use Services\Remember;
use Services\Requests;
use Services\Sanitize;

use Supermarkets\Canadian_Superstore\Groceries\Groceries;

class CanadianSuperstore {

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
        $currency,
        $image;
    
    function __construct(Config $config, Logger $logger, Database $database, Remember $remember=null){
        $this->request = new Requests($config,$logger);
        $this->logger  = $logger;
        $this->config  = $config;
        $this->database = $database;

        $canadian_superstore = $this->config->get('stores.canadian_superstore');

        $this->endpoints = $this->config->get('endpoints.canadian_superstore');
        $this->env = $this->config->get('env');

        $this->sanitize = new Sanitize();

        $this->store_name = $canadian_superstore->name;
        $this->store_type_id = $canadian_superstore->store_type_id;
        $this->user_id = $canadian_superstore->user_id;

        $this->city = $config->get('city');
        $this->remember = $remember;

        $this->image = new Image($config,$logger,$this->request);

        $this->currency = $canadian_superstore->currency;

    }


    public function store_type(){
        $store_type = new StoreTypeModel($this->database);

        $store = $store_type->where(['id' => $this->store_type_id ])->get()[0] ?? null;

        if(is_null($store)){
            $store_type->id = $this->store_type_id;
            $store_type->name = $this->store_name;
            $store_type->user_id = $this->user_id;
            $store_type->large_logo = $this->image->save('asda','https://dynl.mktgcdn.com/p/uxpSIwyZRALdFsUMpGERiKVVeUVlEaMMTBvKbuOZB-E/150x150.png','large','logos');
            $store_type->small_logo =  $this->image->save('asda','https://dynl.mktgcdn.com/p/uxpSIwyZRALdFsUMpGERiKVVeUVlEaMMTBvKbuOZB-E/150x150.png','small','logos');
            $store_type->save();  
        }
 
    }

    public function groceries(){
        $groceries = new Groceries($this->config,$this->logger,$this->database,$this->remember);
        $groceries->groceries();
    }
}

?>