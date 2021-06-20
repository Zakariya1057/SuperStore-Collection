<?php
 
namespace Models\Featured;

use Models\Model;
use Services\DatabaseService;

class FeaturedModel extends Model {

    public $featured_id, $type, $store_type_id, $region_id, $week, $year;

    function __construct(DatabaseService $database_service=null){

        parent::__construct($database_service);

        $this->table('featured_items');

        $fields = [
            'featured_id' => [],
            'type' => [],

            'store_type_id' => [],

            'region_id' => [],

            'week' => [],
            'year' => [],
        ];

        $this->fields($fields);

    }

}

?>