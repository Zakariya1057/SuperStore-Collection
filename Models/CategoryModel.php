<?php
// Create Model Class. Use For Inserting Into Databas. Searching And Deleting.
namespace Models;

require_once __DIR__.'/../vendor/autoload.php';

use Shared\Database;
use Shared\Loggers;
use Models\Model;

class CategoryModel extends Model {

    public $database, $logger,$product;
    
    public $id,$name,$store_category_id;

    function __construct($database=null){

        if($database){
            $this->database = $database;
        }

        $log = new Loggers();
        $this->logger = $log->logger_handler;

        $this->table("products");

        $fields = [
            "name" => [
                "nullable" => false
            ],
            "store_category_id" => [
                "nullable" => false,
                "regex"    => "/^\w+$/"
            ]
        ];

        $this->fields($fields);

    }

}

?>