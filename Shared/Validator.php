<?php

namespace Shared;

use Exception;

class Validator extends Sanitize {

    public function validate_fields($validation,$data){

        $validator_rules = array(
            'int'=> '/^\w+$/',
            'price' => '/^\d{0,8}(\.\d{1,4})?$/',
            'gram_weight' => '/^\d+g$/',
            'rating' => '/^\d{0,8}(\.\d{1,4})?$/',
        );

        foreach($validation as $field_name => $validate){

            $field = $data[$field_name];


            $nullable = $validate['nullable'] ?? false;
            $type = $validate['type'] ?? null;
            $regex = null;

            $max_length = $validate['max_length'] ?? null;

            $range = $validate['range'] ?? null;

            if(!is_null($range)){
                $min = $range['min'];
                $max = $range['max'];

                if($field < $min || $field > $max){
                    throw new Exception("Field($field) $field_name Exceedes Ranges");
                }
            }

            if(!is_null($max_length) && strlen($field) > $max_length){
                throw new Exception("Field $field_name Too Long. Value: $field. Length: $max_length");
            }

            if(!key_exists($field_name,$data)){
                throw new Exception("Field($field) $field_name Not Found");
            }

            if(!$nullable && is_null($field)){
                throw new Exception("$field_name($field) Cannot Be Null");
            }

            if(!is_null($type)){
                $regex = $validator_rules[$type] ?? null;
            }
            
            if(!is_null($regex)){
                preg_match($regex,$field,$matches);

                if(!$matches){
                    throw new Exception("$field_name($field) Failed Regex Validation: $regex");
                }
            }

        }
        
    }

    public function field_length(){
        
    }

    public function field_regex(){

    }

}

?>