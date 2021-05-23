<?php
 
namespace Models\Store;

use Models\Model;
use Services\DatabaseService;
class FacilityModel extends Model {

    public $store_id, $name;
    
    function __construct(DatabaseService $database_service=null){

        parent::__construct($database_service);

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