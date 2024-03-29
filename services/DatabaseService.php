<?php

namespace Services;

use Exception;
use Monolog\Logger;

class DatabaseService {

    private $config_service, $connection, $database_config, $log_query;

    public $logger;

    function __construct(ConfigService $config_service, Logger $logger) {

        $this->logger = $logger;
        $this->config_service = $config_service;
        
        $this->logger->notice('Connecting To Database');

        $database_service_env = $config_service->get('database.env');
        $this->logger->info('Database Environment: '. ucwords($database_service_env));

        $this->database_config = $config_service->get('database.'.$database_service_env);

        $this->database_connect();

        if(!$this->connection){
            throw new Exception("Failed to connect to MySQL Database: " . $this->connect_error);
        } else {
            $this->logger->notice("Successfully Connect To Database");
        }

        $this->log_query = $config_service->get('log_query');
    }

    private function database_connect(){
        $host    = $this->database_config->host;
        $username = $this->database_config->username;
        $password = $this->database_config->password;
        $database = $this->database_config->database;

        $this->logger->debug('Initialising Database Connection.');

        $this->connection = new \mysqli($host, $username, $password, $database);

    }

    public function connect(){
        return $this->connection;
    }

    public function query($query){

        try {

            if(is_null($query)){
                throw new Exception('No Query Specified');
            }

            if($this->log_query){
                $this->logger->debug("Query: $query");
            }

            $database_service_results = $this->connection->query($query);
            return $this->process_results( $database_service_results );

        } catch(Exception $e){
            $error = $e->getMessage();
            $this->logger->error('Query Error: '. $error);

            if ($this->connection->ping()) {
                $this->logger->debug('Connection Is OK');
                throw new Exception($error);
            } else {
                $this->logger->error('Connection To MYSQL Server Has Gone Away');
                
                $retry_config = $this->config_service->get('retry.database');

                $retry_times = $retry_config->attempts;
                $wait = $retry_config->wait;

                $connection_successfull = false;

                $this->logger->error('Reattempting To Connect To MYSQL Server');
                
                for($i =0;$i < $retry_times;$i++){

                    $this->database_connect();

                    if($this->connection->ping()) {
                        $this->logger->debug('Successfully Reconnected To Database');
                        $connection_successfull = true;
                        break;
                    } else {
                        $this->logger->debug('Failed To Reconnect To Database. Trying Again Shortly');
                        $this->logger->debug("Sleeping First For $wait Seconds");
                        sleep($wait);
                    }

                }

                if($connection_successfull){
                    $this->logger->debug('Reattempting Query After Successfull Connection To Database');
                    return $this->process_results( $this->connection->query($query) );
                } else {
                    throw new Exception('Failed To Reconnect To MYSQL Server');
                }
                
            }
            
        }

    }

    private function process_results($results){

        if(is_bool($results)){

            if(!$results){
                throw new Exception($this->connection->error);
            }

            return null;
        } else {
            $results_list = [];

            for($i =0; $i < $results->num_rows; $i++){
                $data = $results->fetch_object();

                foreach($data as $field => $value){

                    if(is_null($value)){
                        $data->{$field} = NULL;
                    } else {
                        $data->{$field} = html_entity_decode($value, ENT_QUOTES);
                    }
                    
                }

                $results_list[] = $data;
            }

            return $results_list;   
        }

    }

    public function insert_id(){
        return $this->connection->insert_id;
    }

    public function start_transaction(){
        $this->logger->notice("- Transaction Begin");
        $this->connection->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);
    }

    public function commit_transaction(){
        $this->logger->notice("- Transaction Complete");
        $this->connection->commit();
    }

    public function transaction_rollback(){
        $this->logger->notice("- Transaction Rollback");
        $this->connection->rollback();
    }

}

?>