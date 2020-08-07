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

    function __construct(){

    }

    public function create($data){
        $data = $this->convert_string_to_array($data);
        //Insert into table() values();

        $table_fields_list = [];
        $insert_fields_list = [];

        $sanitize = new Sanitize();
        $validator = new Validator();

        $data = $sanitize->sanitizeAllFields($data);

        foreach($data as $key => $value){
            $table_fields_list[] = "`$key`";
            $insert_fields_list[] = "'$value'";
            $values_list[] = $value;
        }

        try {
            $validator->validate_fields($this->table_fields,$data);
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
        
        $query = 'SELECT ';

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

        $wheres = [];

        foreach($data as $key => $value){
            $wheres[] = "$key = '$value' ";
        }

        $query = implode(" AND ",$wheres);

        $this->where = $query;

        return $this;
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
            $query .= "INSERT INTO $table_name $create";
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

        foreach($data as $key => $value){
            $wheres[] = "$key = '$value' ";
        }

        $query = implode(", ",$wheres);

        $this->update = $query;

        return $this->run_query();
        
        return $this;

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