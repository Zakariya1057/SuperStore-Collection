<?php
// Create Model Class. Use For Inserting Into Databas. Searching And Deleting.
namespace Models\Category;

class GrandParentCategoryModel extends CategoryModel {

    public $database, $logger,$product;

    function __construct($database=null){
        
        parent::__construct($database);

        $this->table("grand_parent_categories");

        $fields = [
            "name" => [],
            "site_type_id" => [],
            "site_category_id" => [
                "type" => "int"
            ],
        ];

        $this->fields($fields);

    }

}

?>