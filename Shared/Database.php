<?php

namespace Shared;

use Exception;

class Database {

    private $logger, $connection, $database_config,$log_query;

    function __construct($config,$logger) {

        $this->logger = $logger;
        
        $this->logger->notice("Connecting To Database");

        $this->database_config = $config->get('database');

        $host     = $this->database_config->host;
        $username = $this->database_config->username;
        $password = $this->database_config->password;
        $database = $this->database_config->database;

        $this->connection = new \mysqli($host,$username,$password,$database);

        if(!$this->connection){
            throw new Exception("Failed to connect to MySQL Database: " . $this->connect_error);
        } else {
            $this->logger->notice("Successfully Connect To Database");
        }

        $this->log_query = $config->get('log_query');
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

            if($this->log_query){
                $this->logger->debug("Query: $query");
            }
            
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
        $this->query('START TRANSACTION;');
    }

    public function end_transaction(){
        $this->logger->notice("--- Transaction Complete ---");
        $this->query('COMMIT');
    }

}

?>