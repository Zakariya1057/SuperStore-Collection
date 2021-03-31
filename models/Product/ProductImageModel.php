<?php
 
namespace Models\Product;

use Models\Model;

class ProductImageModel extends Model {

    public $name, $size, $product_id;

    function __construct($database=null){

        parent::__construct($database);

        $this->table('product_images');

        $fields = [
            'name' => [],
            'size' => [],
            'product_id' => [
                'type' => 'int'
            ],
        ];

        $this->fields($fields);

    }

}

?>