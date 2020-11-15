<?php

namespace Stores\Asda;

use Exception;

class AsdaGroceries extends Asda {

    function __construct($config,$logger,$database,$remember)
    {
        parent::__construct($config,$logger,$database,$remember);
    }

    public function groceries(){
        // Go to asda page get all categories and pass to categories
        $this->logger->notice("------- Asda Groceries Start --------");
        
        $groceries = $this->groceries_details();

        $category = new AsdaCategories($this->config,$this->logger,$this->database,$this->remember);
        $category->categories($groceries);
        
        $this->logger->notice("------- Asda Groceries Complete --------");
    }

    public function groceries_details(){

        $groceries_endpoint = $this->endpoints->groceries;

        if($this->env == 'dev'){
            $groceries_response = file_get_contents(__DIR__."/../../Data/Asda/Groceries.json");
        } else {
            $groceries_response = $this->request->request($groceries_endpoint);
        }
        
        return $this->request->parse_json($groceries_response);

    }

}

?>