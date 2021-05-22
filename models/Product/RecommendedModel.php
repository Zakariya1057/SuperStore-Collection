<?php
 
namespace Models\Product;

use Models\Model;
use Services\DatabaseService;

class RecommendedModel extends Model {
    
    public $product_id,$recommended_product_id;

    function __construct(DatabaseService $database_service=null){

        parent::__construct($database_service);

        $this->table('recommended');

        $fields = [
            'product_id' => ['type' => 'int'],
            'recommended_product_id' => ['type' => 'int'],
        ];

        $this->fields($fields);

    }

}

?>