<?php

namespace Supermarkets\Canadian_Superstore;

use Exception;
use Monolog\Logger;
use Services\Config;
use Services\Database;
use Services\Image;
use Services\Remember;
use Services\Requests;
use Services\Sanitize;

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

    }

}

?>