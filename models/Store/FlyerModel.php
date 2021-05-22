<?php
 
namespace Models\Store;

use Models\Model;
use Services\DatabaseService;

class FlyerModel extends Model {

    public 
        $name, 
        $week, 

        $store_id, 
        $store_type_id, 

        $url,
        
        $site_flyer_id,

        $valid_from,
        $valid_to;

    function __construct(DatabaseService $database_service=null){

        parent::__construct($database_service);

        $this->table('flyers');

        $fields = [
            'name' => [],

            'week' => [
                'nullable' => true
            ],

            'store_id' => [
                'type' => 'int'
            ],
            'store_type_id' => [
                'type' => 'int'
            ],

            'url' => [],
            
            'site_flyer_id' => [
                'type' => 'int'
            ],

            'valid_from' => [
                'type' => 'date'
            ],
            'valid_to' => [
                'type' => 'date'
            ]
        ];

        $this->fields($fields);

    }

}

?>