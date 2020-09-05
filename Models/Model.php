<?php

namespace Models;

require_once __DIR__.'/../vendor/autoload.php';

use Exception;
use Shared\Database;
use Shared\Loggers;
use Shared\Sanitize;
use Shared\Validator;

class Model {

    private $select, $create, $delete, $where,$update, $limit, $like, $table,$table_fields;

    public $database, $logger,$product, $insert_ignore;

    public $sanitize, $validator;

    function __construct($database=null){
        if($database){
            $this->database = $database;
        }

        $log = new Loggers();
        
        $this->sanitize = new Sanitize();
        $this->validator = new Validator();

        $this->logger = $log->logger_handler;
    }
    
    public function create($data){
        $data = $this->convert_string_to_array($data);
        // print_r($data);

        $table_fields_list = [];
        $insert_fields_list = [];

        $data = $this->sanitize->sanitizeAllFields($data);

        foreach($data as $key => $value){
            $table_fields_list[] = "`$key`";

            if(!is_null($value)){
                $insert_fields_list[] = "'$value'";
            } else {
                $insert_fields_list[] = "NULL";
            }
            
            $values_list[] = $value;
        }

        try {
            $this->validator->validate_fields($this->table_fields,$data);
        } catch(Exception $e) {
            $this->logger->error("Table $this->table Validation Error: ".$e->getMessage());
            throw new Exception($e);
        }
        

        $table_fields = implode(', ',  $table_fields_list);
        $insert_fields = implode(', ', $insert_fields_list);

        $this->create = "($table_fields) VALUES($insert_fields)";

        return $this->run_query();
    }

    public function select($fields=null){
        
        $query = '';

        $fields = $this->convert_string_to_array($fields);

        if(!is_null($fields)){
            $query .= implode(", ", $fields);
        } else {
            $query .= "*";
        }

        $this->select = $query;
        return $this;
    }

    public function selectRaw($data){
        $selects = [];

        $data = $this->convert_string_to_array($data);

        foreach($data as $fields){
            $selects[] = "$fields";
        }

        $query = implode(", ",$selects);

        $this->select = $query;
        return $this;
    }

    public function where($data){

        $query = $this->makeQuery($data);

        $this->where = $query;

        return $this;
    }

    public function orWhere($data){

        $query = $this->makeQuery($data);

        $this->where = "( $this->where ) OR ( $query )";

        return $this;
    }

    public function makeQuery($data,$seperator='AND'){
        
        $wheres = [];

        $data = $this->sanitize->sanitizeAllFields($data);

        foreach($data as $key => $value){
            if(is_null($value) ){
                $wheres[] = "$key is NULL";
            } else {
                $wheres[] = "$key = '$value'";
            }
        }

        return implode(" $seperator ",$wheres);
    }

    public function whereRaw($query){

        $query = $this->convert_string_to_array($query);

        $query = implode(" AND ",$query);
        $this->where = $query;

        return $this;
    }
    
    public function limit($limit=null){
        $this->limit = $limit;
        return $this;
    }

    public function like($data){
        $likes = [];

        $data = $this->convert_string_to_array($data);

        foreach($data as $key => $value){
            $likes[] = "$key LIKE '$value'";
        }

        $query = implode(" AND ",$likes);

        $this->like = $query;
        return $this;
    }

    public function fields($table_fields){
        $this->table_fields = $table_fields;
    }

    public function table($table_name){
        
        if(!is_null($table_name)){
            $this->table = $table_name;
        } else {
            throw new Exception("No Table Name Found");
        }
        
    }

    public function get(){
        return $this->run_query();
    }

    private function run_query(){

        $query = '';

        $select_fields = $this->select;
        $table_name    = $this->table;
        $where_fields  = $this->where;
        $limit         = $this->limit;
        $update        = $this->update;
        $delete        = $this->delete;
        $like          = $this->like;
        $create        = $this->create;
        

        if(!is_null($create)){

            if(!is_null($this->insert_ignore)){
                $query .= "INSERT IGNORE INTO $table_name $create";
            } else {
                $query .= "INSERT INTO $table_name $create";
            }
            
        } else {

            if($delete){
                $query .= "DELETE FROM $table_name ";
            } else {
    
                if(!is_null($update)){
                    $query .= "UPDATE $table_name ";
                } else {
                    if(!is_null($select_fields)){
                        $query .= "SELECT $select_fields FROM $table_name ";
                    } else {
                        $query .= "SELECT * FROM $table_name ";
                    }
                }
        
                if(!is_null($update)){
                    $query .= "SET $update ";
                }
    
            }
    
            if(!is_null($where_fields)){
                if(!is_null($like)){
                    $where_fields .= " AND $like";
                }
    
                $query .= "WHERE $where_fields ";
            } else {
                if(!is_null($like)){
                    $query .= "WHERE $like";
                }
            }
    
            if(!is_null($limit)){
                $query .= "LIMIT $limit";
            }

        }

        $results = $this->database->query($query);
        
        if(!is_null($create)){
            return $this->database->insert_id();
        } else {
            return $results;
        }
        
    }

    public function update($data){

        $wheres = [];

        $sanitize = new Sanitize();

        $data = $sanitize->sanitizeAllFields($data);

        foreach($data as $key => $value){
            $wheres[] = "`$key` = '$value' ";
        }

        $query = implode(", ",$wheres);

        $this->update = $query;

        return $this->run_query();
        
        return $this;

    }

    public function save(){

        $data = [];

        foreach($this->table_fields as $name => $validation){
            $data[$name] = $this->{$name};
        }
        
        $this->create($data);

        return $this->database->insert_id();

    }


    public function delete(){
        $this->delete = true;
        return $this->run_query();
    }

    private function convert_string_to_array($data){
        if(!is_null($data) && !is_array($data)){
            return [$data];
        }  else {
            return $data;
        }
    }
}

?>