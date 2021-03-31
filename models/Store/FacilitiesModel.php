<?php
 
namespace Models\Store;

use Models\Model;

class FacilitiesModel extends Model {

    public $store_id, $name;
    
    function __construct($database=null){

        parent::__construct($database);

        $this->table("facilities");

        $fields = [
            'store_id' => [
                'type' => 'int'
            ],
            'name' => [],

        ];

        $this->fields($fields);

    }

}

?>