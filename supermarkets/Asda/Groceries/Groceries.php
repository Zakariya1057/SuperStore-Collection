<?php

namespace Supermarkets\Asda\Groceries;

use Exception;
use Monolog\Logger;
use Services\Config;
use Services\Database;
use Services\Remember;
use Supermarkets\Asda\Asda;
use Supermarkets\Asda\Groceries\Categories\Categories;

class Groceries extends Asda {

    function __construct(Config $config, Logger $logger, Database $database, Remember $remember=null)
    {
        parent::__construct($config,$logger,$database,$remember);
    }

    public function groceries(){
        // Go to asda page get all categories and pass to categories
        $this->logger->notice("------- Asda Groceries Start --------");
        
        $groceries = $this->groceries_details();

        $category = new Categories($this->config,$this->logger,$this->database,$this->remember);
        $category->categories($groceries);
        
        $this->logger->notice("------- Asda Groceries Complete --------");
    }

    public function groceries_details(){

        $groceries_endpoint = $this->endpoints->groceries;

        if($this->env == 'dev'){
            $groceries_response = file_get_contents(__DIR__."/../../data/Asda/Groceries.json");
        } else {
            $groceries_response = $this->request->request($groceries_endpoint);
        }
        
        return $this->request->parse_json($groceries_response);

    }

}

?>