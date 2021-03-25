<?php
 
namespace Models\Product;

use Models\Model;

class BarcodeModel extends Model {

    public $type, $value, $product_id, $store_type_id;
    
    function __construct($database=null){

        parent::__construct($database);

        $this->table('barcodes');

        $fields = [
            'type' => [],
            'value' => [],

            'product_id' => ['type' => 'int'],
            'store_type_id' => ['type' => 'int'],
        ];

        $this->fields($fields);

    }

}

?>