<?php
 
namespace Models\Store;

use Shared\Loggers;
use Models\Model;

//Each store location.
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
                'nullable' => true
            ],

            'opens_at' => [
                'type' => 'time'
            ],

            'closes_at' => [
                'type' => 'time'
            ]

        ];

        $this->fields($fields);

    }

}

?>