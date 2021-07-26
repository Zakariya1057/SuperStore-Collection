<?php
 
namespace Models\Product;

use Models\Model;
use Services\DatabaseService;

class BarcodeModel extends Model {

    public $type, $value, $product_id, $company_id;
    
    function __construct(DatabaseService $database_service=null){

        parent::__construct($database_service);

        $this->table('barcodes');

        $fields = [
            'type' => [],
            'value' => [],

            'product_id' => ['type' => 'int'],
            'company_id' => ['type' => 'int'],
        ];

        $this->fields($fields);

    }

}

?>