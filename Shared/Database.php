<?php

namespace Shared;

require_once __DIR__.'/../vendor/autoload.php';

use Shared\Sanitize;
use Shared\Config;
use Shared\Loggers;

use Exception;

class Database {

    private $logger, $connection, $database_config,$sanitizer;

    function __construct() {
        $conf = new Config();
        
        $this->sanitizer = new Sanitize();

        $logger = new Loggers();
        $this->logger = $logger->logger_handler;
        
        $this->logger->debug("Connecting To Database");

        $this->database_config = $conf->get('database');

        $host     = $this->database_config->host;
        $username = $this->database_config->username;
        $password = $this->database_config->password;
        $database = $this->database_config->database;

        $this->connection = new \mysqli($host,$username,$password,$database);

        if(!$this->connection){
            throw new Exception("Failed to connect to MySQL Database: " . $this->connect_error);
        } else {
            $this->logger->debug("Successfully Connect To Database");
        }
    }

    public function connect(){
        return $this->connection;
    }

    public function query($query){
        $conn = $this->connection;

        try {

            if(is_null($query)){
                throw new Exception("No Query Specified");
            }

            $this->logger->notice("Query: $query");

            $results = $conn->query($query);
            
            if(is_bool($results)){

                if(!$results){
                    throw new Exception($conn->error);
                }

                return null;
            } else {
                return $results->fetch_object();
            }

        } catch(Exception $e){
            $error = $e->getMessage();
            $this->logger->error("Query Error: ". $error);
            throw new Exception($error);
        }
    }

    public function insert_id(){
        return $this->connection->insert_id;
    }

    public function start_transaction(){
        $this->logger->notice("--- Transaction Begin ---");
    }

    public function end_transaction(){
        $this->logger->notice("--- Transaction Complete ---");
    }

}

// $db = new Database();
// $results = $db->query("SELECT * from s");

// print_r($results);

?>