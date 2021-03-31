<?php
 
namespace Models\Store;

use Models\Model;

class OpeningHoursModel extends Model {

    public $store_id, $day_of_week, $opens_at,$closes_at,$closed_today;

    function __construct($database=null){

        parent::__construct($database);

        $this->table("opening_hours");

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