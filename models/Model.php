<?php

namespace Models;

require_once __DIR__.'/../vendor/autoload.php';

use Exception;
use Services\DatabaseService;
use Services\SanitizeService;
use Services\ValidatorService;

class Model {

    private $select, $create, $delete, $where, $regex, $group_by, $update, $offset, $limit, $like, $join, $not_in, $order, $table, $table_fields;

    public $database_service, $logger,$product, $insert_ignore;

    private $sanitize_service, $validator_service;

    private $cast;

    function __construct(DatabaseService $database_service=null){
        if($database_service){
            $this->database_service = $database_service;
            $this->logger = $database_service->logger;
        }
        
        $this->sanitize_service = new SanitizeService();
        $this->validator_service = new ValidatorService();
    }
    
    public function create($data){
        $data = $this->convert_string_to_array($data);

        $table_fields_list = [];
        $insert_fields_list = [];
        
        $sanitized_data = $this->sanitize_service->sanitize_fields($data);

        foreach($sanitized_data as $key => $value){
            $validation = $this->table_fields[$key];

            if(key_exists('ignore', $validation)){
                $this->logger->debug('Ignoring Field: ' . $key);
            } else {

                $table_fields_list[] = "`$key`";

                if( key_exists('exclude_sanitize', $validation) && $validation['exclude_sanitize'] ){
                    $value = $data[$key]; 
                } 
                
                if(is_null($value)){
                    $insert_fields_list[] = "NULL";
                } else {
                    $insert_fields_list[] = "'$value'";
                }

            }

        }

        try {
            $this->validator_service->validate_fields($this->table_fields,$sanitized_data);
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

        $this->select = trim($query);
        return $this;
    }

    public function select_raw($data){
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

        $query = $this->create_query($data);

        if(is_null($this->where)){
            $this->where = $query;
        } else {
            $this->where = $this->where . " AND $query";            
        }

        return $this;
    }

    public function where_raw($query){

        $query = $this->convert_string_to_array($query);
        $query = implode(" AND ",$query);

        if(is_null($this->where)){
            $this->where = $query;
        } else {
            $this->where = $this->where . " AND $query";            
        }

        return $this;
    }

    public function or_where($data){

        $query = $this->create_query($data);

        $this->where = "( $this->where ) OR ( $query )";

        return $this;
    }

    public function create_query($data, $seperator='AND'){
        
        $wheres = [];

        $data = $this->sanitize_service->sanitize_fields($data);

        foreach($data as $key => $value){
            if(is_null($value) ){
                $wheres[] = "$key is NULL";
            } else {
                $wheres[] = "$key = '$value'";
            }
        }

        return implode(" $seperator ",$wheres);
    }

    public function group_by($field){
        $this->group_by = $field;
        return $this;
    }
    
    public function limit(int $limit){
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset){
        $this->offset = $offset;
        return $this;
    }

    public function order_by($field, $order = 'DESC'){
        if(is_null($order)){
            $order = '';
        } else {
            $order = strtoupper($order);

            if($order != 'ASC' && $order != 'DESC'){
                throw new Exception('Unknown Order By: '.$order);
            }
        }

        $this->order = "ORDER BY $field $order";

        return $this;
    }

    public function join($table, $relationship_1, $relationship_2, $join_type = 'LEFT'){

        $join_type = strtoupper($join_type);

        if($join_type != 'LEFT' && $join_type != 'RIGHT' && $join_type != 'INNER' && $join_type != 'FULL' && $join_type != 'SELF'){
            throw new Exception('Unknown Database Join: ' . $join_type);
        }

        $this->join[] = "$join_type JOIN $table ON $relationship_1 = $relationship_2";

        return $this;
    }

    public function where_not_in($field, $list){

        $query_list = [];

        foreach($list as $item){
            $query_list[] = "'$item'";
        }

        $query_list = join(',', $query_list);

        if(is_null($this->where)){
            $this->not_in = "WHERE `$field` NOT IN ($query_list)";
        } else {
            $this->where .= " AND `$field` NOT IN ($query_list)";
        }
        
        return $this;
    }

    public function where_in($field, $list){

        $query_list = [];

        foreach($list as $item){
            $query_list[] = "'$item'";
        }

        $query_list = join(',', $query_list);

        if(is_null($this->where)){
            $this->where = "$field IN ($query_list)";
        } else {
            $this->where = $this->where . " AND $field IN ($query_list)";
        }

        return $this;
    }

    public function like($data){
        $likes = [];

        $data = $this->convert_string_to_array($data);

        foreach($data as $key => $value){
            if(is_null($value)){
                $likes[] = "$key LIKE NULL";
            } else {
                $likes[] = "$key LIKE '$value'";
            }
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
            throw new Exception('No Table Name Found');
        }
        
    }

    public function regex($field, $data, $join = '|'){
        $query = join($join, $data);

        $this->where = "$field REGEXP '$query'";

        return $this;
    }

    public function first(){
        return $this->get()[0] ?? null;
    }

    public function get(){
        $results = $this->run_query();

        if(!is_null($this->cast)){
            foreach($results as $index => $item){
                
                $model = new $this->cast($this->database_service);

                $fields = array_keys($this->table_fields);
                $fields[] = 'id';

                foreach($fields as $field){
                    $model->{$field} = $item->{$field};
                }
                
                $results[$index] = $model;
            }

            $this->cast = null;
        }

        return $results;
    }

    private function run_query(){

        $queries = [];

        $select_fields = $this->select;
        $table_name    = $this->table;
        $where_fields  = $this->where;
        $update        = $this->update;
        $delete        = $this->delete;
        $like          = $this->like;
        $create        = $this->create;
        $group_by      = $this->group_by;
        $join          = $this->join;
        $order         = $this->order;
        $not_in        = $this->not_in;
        $limit         = $this->limit;
        $offset        = $this->offset;

        if(!is_null($create)){

            if(!is_null($this->insert_ignore)){
                $queries[] = "INSERT IGNORE INTO $table_name $create";
            } else {
                $queries[] = "INSERT INTO $table_name $create";
            }
            
        } else {

            if($delete){
                $queries[] = "DELETE FROM $table_name";
            } else {
    
                if(!is_null($update)){
                    $queries[] =  "UPDATE $table_name";
                } else {
                    if(!is_null($select_fields)){
                        $queries[] =  "SELECT $select_fields FROM $table_name";
                    } else {
                        $queries[] =  "SELECT * FROM $table_name";
                    }
                }
        
                if(!is_null($update)){
                    $queries[] =  "SET $update";
                }
    
            }
    
            if(!is_null($join)){
                $queries[] = join(' ', $join);
            }

            if(!is_null($where_fields)){
                if(!is_null($like)){
                    $where_fields .= " AND $like";
                }
    
                $queries[] =  "WHERE $where_fields";
            } else {
                if(!is_null($like)){
                    $queries[] =  "WHERE $like";
                }
            }

            if(!is_null($not_in)){
                $queries[] =  "$not_in";
            }

            if(!is_null($group_by)){
                $queries[] =  "GROUP BY $group_by";
            }

            if(!is_null($order)){
                $queries[] =  "$order";
            }

            if(!is_null($limit)){
                $queries[] =  "LIMIT $limit";
            }

            if(!is_null($offset)){
                $queries[] =  "OFFSET $offset";
            }

        }

        $query = join(" ", $queries);

        $results = $this->database_service->query($query);

        $this->reset_data();

        if(!is_null($create)){
            return $this->database_service->insert_id();
        } else {
            return $results;
        }
        
    }

    public function cast($model){
        // Return As The Model
        $this->cast = $model;
        return $this;
    }

    public function update($data){

        $wheres = [];

        $sanitized_data = $this->sanitize_service->sanitize_fields($data);

        foreach($sanitized_data as $key => $value){
            if(is_null($value) || trim($value) == ''){
                $wheres[] = "`$key` = NULL ";
            } else {
                if(key_exists('exclude_sanitize', $this->table_fields[$key]) && $this->table_fields[$key]['exclude_sanitize'] ){
                    $value = $data[$key]; 
                }

                $wheres[] = "`$key` = '$value' ";
            }
        }

        $query = implode(", ",$wheres);

        $this->update = $query;

        return $this->run_query();
        
        return $this;

    }

    public function save(){

        $data = [];

        foreach($this->table_fields as $name => $validation){
            // If this is ignore and its empty then its fine, just ignore it
            if(!( (key_exists('ignore', $validation) && $validation['ignore']) && (key_exists($name, $data) && is_null($data[$name])) )){
                $data[$name] = $this->{$name};
            }
        }

        $this->reset_data();
        
        $this->create($data);

        return $this->database_service->insert_id();

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

    private function reset_data(){
        $this->where = 
        $this->regex = 
        $this->not_in = 
        $this->like = 
        $this->limit = 
        $this->update = 
        $this->join = 
        $this->order = 
        $this->group_by = 
        $this->create = 
        $this->delete = 
        $this->select =
        $this->limit =
        $this->offset =
        null;
    }
}

?>