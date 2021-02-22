<?php

namespace Supermarkets\Asda\Groceries\Categories;

use Monolog\Logger;
use Services\Config;
use Services\Database;
use Services\Remember;

class Departments extends Categories {

    public $aisle;

    function __construct(Config $config, Logger $logger, Database $database, Remember $remember=null)
    {
        parent::__construct($config,$logger,$database,$remember);
        $this->aisles = new Aisles($config,$logger,$database,$remember);
    }

    public function create($department_item,$grand_parent_category_id){
        $department_item->parent_category_id = $grand_parent_category_id;

        $department_details = $this->select_category($department_item,"parent");
        $this->logger->notice("-- Department: $department_details->name");

        $last_category_index = $this->remember->get('child_category_index') ?? 0;

        $categories_list = array_slice($department_item->subcategories,$last_category_index);

        if(count($categories_list) != 0){

            $first_category = $categories_list[0];
            $this->logger->debug("Starting With Child Category: [$last_category_index] " . $first_category->displayName);
    
            foreach($categories_list as $index => $aisle){
                $this->remember->set('child_category_index',$index + $last_category_index);
                $this->aisles->create($aisle,$grand_parent_category_id, $department_details->id);
            }

            $this->remember->set('child_category_index',0);

        }

    }

}

?>