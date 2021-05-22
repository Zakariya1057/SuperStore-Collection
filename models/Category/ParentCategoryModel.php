<?php
 
namespace Models\Category;

use Services\DatabaseService;

class ParentCategoryModel extends CategoryModel {

    public $database_service, $logger,$product;

    function __construct(DatabaseService $database_service=null){

        parent::__construct($database_service);

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