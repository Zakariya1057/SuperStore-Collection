<?php
 
namespace Models\Product;

use Models\Model;
use Services\DatabaseService;

class IngredientModel extends Model {

    public $database_service, $logger,$product;

    public $product_id, $name;
    
    function __construct(DatabaseService $database_service=null){

        parent::__construct($database_service);

        $this->table('ingredients');

        $fields = [
            'name' => ['max_range' => 500],
            'product_id' => ['type' => 'int'],
        ];

        $this->fields($fields);

    }

}

?>