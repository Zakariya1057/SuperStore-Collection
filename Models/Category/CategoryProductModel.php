<?php
// Create Model Class. Use For Inserting Into Databas. Searching And Deleting.
namespace Models\Category;

use Models\Model;

class CategoryProductModel extends Model {

    public 
    $product_id,
    $child_category_id,
    $parent_category_id,
    $grand_parent_category_id,
    $store_id;

    function __construct($database=null){

        parent::__construct($database);

        $this->table('category_products');

        $fields = [
            'product_id' => ['type' => 'int'],
            'child_category_id' => ['type' => 'int'],
            'parent_category_id' => ['type' => 'int'],
            'grand_parent_category_id' => ['type' => 'int'],
            'store_id' => ['nullable' => true],
        ];

        $this->fields($fields);

    }

}

?>