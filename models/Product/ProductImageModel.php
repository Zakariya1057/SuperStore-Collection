<?php
 
namespace Models\Product;

use Models\Model;
use Services\DatabaseService;

class ProductImageModel extends Model {

    public $name, $size, $product_id;

    function __construct(DatabaseService $database_service=null){

        parent::__construct($database_service);

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