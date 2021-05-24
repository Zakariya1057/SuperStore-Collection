<?php
 
namespace Models\Store;

use Models\Model;
use Services\DatabaseService;

class OpeningHourModel extends Model {

    public $store_id, $day_of_week, $opens_at,$closes_at,$closed_today;

    function __construct(DatabaseService $database_service=null){

        parent::__construct($database_service);

        $this->table('opening_hours');

        $fields = [
            'store_id' => [
                'type' => 'int'
            ],

            'day_of_week' => [
                'range' => [
                    'min' => 0,
                    'max' => 6
                ]
            ],

            'closed_today' => [
                'nullable' => false
            ],

            'opens_at' => [
                'nullable' => true,
                'type' => 'time'
            ],

            'closes_at' => [
                'nullable' => true,
                'type' => 'time'
            ]

        ];

        $this->fields($fields);

    }

}

?>