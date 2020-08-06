<?php

namespace Stores\Asda;

use Shared\Request;

class AsdaGroceries {

    private $logger,$request,$config;

    function __construct($config,$logger,$database){
        $this->request = new AsdaRequests($config,$logger);
        $this->logger  = $logger;
        $this->config  = $config;
        $this->database = $database;
    }

    public function groceries(){
        // Go to asda page get all categories and pass to categories
        $logger = $this->logger;
        $config = $this->config;
        $request = $this->request;

        $logger->notice("------- Asda Groceries Start --------");
        
        $groceries = $this->request->groceries();

        $category = new AsdaCategories($config,$logger,$this->database);
        $category->categories($groceries);
        
        $logger->notice("------- Asda Groceries Complete --------");
    }

}

?>