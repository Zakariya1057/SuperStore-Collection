<?php
// Create Model Class. Use For Inserting Into Databas. Searching And Deleting.
namespace Models;

require_once __DIR__.'/../vendor/autoload.php';

use Shared\Loggers;

class ParentCategoryModel extends CategoryModel {

    public $database, $logger,$product;

    function __construct($database=null){

        if($database){
            $this->database = $database;
        }

        $log = new Loggers();
        $this->logger = $log->logger_handler;

        $this->table("parent_categories");

        $fields = [
            "name" => [],
            "parent_id" => [],
            "site_id" => [],
            "site_category_id" => [
                "type" => "int"
            ],
        ];

        $this->fields($fields);

    }

}

?>