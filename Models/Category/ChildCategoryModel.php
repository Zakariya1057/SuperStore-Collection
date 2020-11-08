<?php
 
namespace Models\Category;

class ChildCategoryModel extends CategoryModel {

    function __construct($database=null){

        parent::__construct($database);

        $this->table("child_categories");

        $fields = [
            "name" => [],
            "parent_category_id" => [],
            "store_type_id" => [],
            "site_category_id" => [
                "type" => "int"
            ],
        ];

        $this->fields($fields);

    }

}

?>