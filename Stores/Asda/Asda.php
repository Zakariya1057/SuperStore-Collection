<?php

namespace Stores\Asda;

use Models\ProductModel;
use Shared\Requests;
use Models\ReviewModel;
use Shared\Sanitize;
use Shared\WeightConverter;

class Asda {

    public $logger,$request,$config,$database,$endpoints,$env,$sanitize,$weight_converter,$site_type_id,$user_id,$city;

    function __construct($config,$logger,$database){
        $this->request = new Requests($config,$logger);
        $this->logger  = $logger;
        $this->config  = $config;
        $this->database = $database;

        $asda_conf = $this->config->get('asda');

        $this->endpoints = $this->config->get("endpoints")->asda;
        $this->env = $this->config->get("env");

        $this->sanitize = new Sanitize();
        $this->weight_converter = new WeightConverter();

        $this->site_type_id = $asda_conf->site_type_id;
        $this->user_id = $asda_conf->user_id;
        $this->city = $config->get('city');

    }

    public function recommended(){
        $recommended = new AsdaRecommended($this->config,$this->logger,$this->database);
        $recommended->all_recommended_products();
    }

    public function groceries(){
        $groceries = new AsdaGroceries($this->config,$this->logger,$this->database);
        $groceries->groceries();
    }

    public function promotions(){
        $promotions = new AsdaPromotions($this->config,$this->logger,$this->database);
        $promotions->promotions();
    }

    public function stores(){
        $stores = new AsdaStores($this->config,$this->logger,$this->database);
        $stores->stores();
    }

}

?>