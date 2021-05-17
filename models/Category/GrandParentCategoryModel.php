<?php
 
namespace Models\Category;

class GrandParentCategoryModel extends CategoryModel {

    public $database, $logger,$product;

    function __construct($database=null){
        
        parent::__construct($database);

        $this->table('grand_parent_categories');

        $fields = [
            'name' => [],
            'store_type_id' => [],
            'site_category_id' => [],

            'index' => ['type' => 'int'],
        ];

        $this->fields($fields);

    }

}

?>