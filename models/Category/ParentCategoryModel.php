<?php
 
namespace Models\Category;

class ParentCategoryModel extends CategoryModel {

    public $database, $logger,$product;

    function __construct($database=null){

        parent::__construct($database);

        $this->table('parent_categories');

        $fields = [
            'name' => [],
            'parent_category_id' => [],
            'store_type_id' => [],
            'site_category_id' => [],

            'index' => ['type' => 'int'],
        ];

        $this->fields($fields);

    }

}

?>