<?php

namespace Collection\Loblaws;

use Collection\Loblaws\Groceries\Groceries;
use Collection\Loblaws\Groceries\Recommended\Recommended;
use Collection\Loblaws\Stores\Stores;

use Services\ConfigService;
use Services\DatabaseService;
use Services\ImageService;
use Services\RememberService;
use Services\RequestService;
use Services\SanitizeService;

use Monolog\Logger;

class Loblaws {

    public 
        $logger,

        $request_service,
        $config_service,

        $remember_service,

        $database_service,
        $sanitize_service,

        $endpoints,
        $env,

        $loblaws_config,

        $company_id,
        $company_name,
        $supermarket_chain_id,
        $user_id,

        $supermarket_chains,

        $currency,

        $image;

    function __construct(ConfigService $config_service, Logger $logger, DatabaseService $database_service, RememberService $remember_service=null){
        $this->request_service = new RequestService($config_service, $logger);
        $this->logger = $logger;
        $this->config_service = $config_service;
        $this->database_service = $database_service;

        $this->sanitize_service = new SanitizeService();

        $this->loblaws_config = $this->config_service->get('companies.loblaws');

        $remember_service = new RememberService($config_service, $logger, $database_service);
        $remember_service->company_id = $this->loblaws_config->id;
        $remember_service->retrieve_data();

        $this->remember_service = $remember_service;
        $this->image_service = new ImageService($config_service, $logger, $this->request_service);

        $this->endpoints = $this->config_service->get('endpoints.loblaws');
        $this->env = $this->config_service->get('env');

        $this->company_id = $this->loblaws_config->id;
        $this->company_name = $this->loblaws_config->name;
        $this->user_id = $this->loblaws_config->user_id;
        $this->currency = $this->loblaws_config->currency;

        $this->supermarket_chains = $this->loblaws_config->supermarket_chains;
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