<?php

namespace Stores\Asda;

use Shared\Request;

class AsdaPromotions {

    private $logger,$request,$config;

    function __construct($config,$logger){
        $this->request = new AsdaRequests($config,$logger);
        $this->logger  = $logger;
        $this->config  = $config;
    }

    public function details(){
        // Get details about promotion, like expire and start date. Get all products involved.
    }

    public function new_promotions(){
        //Search for new offers not in database.
    }

}

?>