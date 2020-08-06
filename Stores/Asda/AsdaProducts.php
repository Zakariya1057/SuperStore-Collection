<?php

namespace Stores\Asda;

use Shared\Request;

class AsdaProducts {

    private $logger,$request,$config,$database;

    function __construct($config,$logger,$database){
        $this->request = new AsdaRequests($config,$logger);
        $this->logger  = $logger;
        $this->config  = $config;
        $this->database = $database;
    }

    public function product($product_id){

    }

    public function reviews($product_id){
        
    }

}

?>