<?php
// Create Model Class. Use For Inserting Into Databas. Searching And Deleting.
namespace Models\Product;

use Models\Model;

class IngredientModel extends Model {

    public $database, $logger,$product;

    public $product_id, $name;
    
    function __construct($database=null){

        parent::__construct($database);

        $this->table("ingredients");

        $fields = [
            'name' => ['max_range' => 500],
            'product_id' => ['type' => 'int'],
        ];

        $this->fields($fields);

    }

}

?>