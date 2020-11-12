<?php
 
namespace Models\Product;

use Models\Model;

class RecommendedModel extends Model {
    
    public $product_id,$recommended_product_id;

    function __construct($database=null){

        parent::__construct($database);

        $this->table('recommended');

        $fields = [
            'product_id' => ['type' => 'int'],
            'recommended_product_id' => ['type' => 'int'],
        ];

        $this->fields($fields);

    }

}

?>