<?php
 
namespace Models\Flyer;

use Models\Model;
use Services\DatabaseService;

class FlyerProductModel extends Model {

    public 
        $flyer_id, 
        $product_id;

    function __construct(DatabaseService $database_service=null){

        parent::__construct($database_service);

        $this->table('flyer_products');

        $fields = [
            'flyer_id' => [],
            'product_id' => []
        ];

        $this->fields($fields);

    }

}

?>