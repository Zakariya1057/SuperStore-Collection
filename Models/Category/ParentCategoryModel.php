<?php
// Create Model Class. Use For Inserting Into Databas. Searching And Deleting.
namespace Models\Category;

require_once __DIR__.'/../vendor/autoload.php';

use Shared\Loggers;

class ParentCategoryModel extends CategoryModel {

    public $database, $logger,$product;

    function __construct($database=null){

        parent::__construct($database);

        $this->table("parent_categories");

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