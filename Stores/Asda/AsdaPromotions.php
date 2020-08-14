<?php

namespace Stores\Asda;

use Shared\Request;
use Shared\Requests;
use Exception;

class AsdaPromotions extends Asda {

    function __construct($config,$logger,$database)
    {
        parent::__construct($config,$logger,$database);
    }

    public function promotion_details(){
        // Get details about promotion, like expire and start date. Get all products involved.
    }

    public function promotions(){
        //Search for new offers not in database.
    }

}

?>