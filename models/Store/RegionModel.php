<?php
 
namespace Models\Store;

use Models\Model;
use Services\DatabaseService;

class RegionModel extends Model {

    public 
        $name, 
        $country,
        $store_type_id;

    function __construct(DatabaseService $database_service=null){

        parent::__construct($database_service);

        $this->table('regions');

        $fields = [
            'name' => [],
            'country' => [],
            'store_type_id' => []
        ];

        $this->fields($fields);

    }

}

?>