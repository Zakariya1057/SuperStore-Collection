<?php
 
namespace Models\Category;

use Models\Model;

class ProductGroupModel extends Model {

    public $name, $child_category_id, $site_product_group_id, $store_type_id;

    function __construct($database=null){

        parent::__construct($database);

        $this->table('product_groups');

        $fields = [
            'name' => [],
            'child_category_id' => [],
            'store_type_id' => [],
            'site_product_group_id' => []
        ];

        $this->fields($fields);

    }

}

?>