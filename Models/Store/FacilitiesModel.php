<?php
// Create Model Class. Use For Inserting Into Databas. Searching And Deleting.
namespace Models\Store;

use Shared\Loggers;
use Models\Model;

//Each store location.
class FacilitiesModel extends Model {

    // Fuel

    // Car Park

    // Cash Machine

    // Customer WC

    // Disabled Facilities

    // Baby Changing

    // Paypoint

    // Electric Vehicle Charging Point

    // Photo Cake Machines

    // Helium Balloons

    // ATM

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