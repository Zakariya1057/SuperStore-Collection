<?php

namespace Models\Shared;

use Models\Model;

class MonitoredProductModel extends Model {

    public $product_id, $user_id;
    
    function __construct($database=null){

        parent::__construct($database);

        $this->table('monitored_products');

        $fields = [
            'product_id' => [
                'type' => 'int'
            ],

            'user_id' => [
                'type' => 'int'
            ],
        ];

        $this->fields($fields);

    }

}

?>