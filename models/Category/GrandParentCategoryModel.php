<?php
 
namespace Models\Category;

use Services\DatabaseService;
class GrandParentCategoryModel extends CategoryModel {

    public $database_service, $logger,$product;

    function __construct(DatabaseService $database_service=null){
        
        parent::__construct($database_service);

        $this->table('grand_parent_categories');

        $fields = [
            'name' => [],
            'company_id' => [],
            'site_category_id' => [],

            'index' => ['type' => 'int'],
        ];

        $this->fields($fields);

    }

}

?>