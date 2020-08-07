<?php
// Create Model Class. Use For Inserting Into Databas. Searching And Deleting.
namespace Models;

require_once __DIR__.'/../vendor/autoload.php';

use Shared\Database;
use Shared\Loggers;
use Models\Model;

class ReviewModel extends Model {

    public $database, $logger,$product;
    
    
    function __construct($database=null){

        if($database){
            $this->database = $database;
        }
        
        $log = new Loggers();
        $this->logger = $log->logger_handler;

        $this->table("reviews");

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