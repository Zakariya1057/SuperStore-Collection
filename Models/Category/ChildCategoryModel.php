<?php
// Create Model Class. Use For Inserting Into Databas. Searching And Deleting.
namespace Models\Category;

class ChildCategoryModel extends CategoryModel {

    function __construct($database=null){

        parent::__construct($database);

        $this->table("child_categories");

        $fields = [
            "name" => [],
            "parent_id" => [],
            "site_type_id" => [],
            "site_category_id" => [
                "type" => "int"
            ],
        ];

        $this->fields($fields);

    }

}

?>