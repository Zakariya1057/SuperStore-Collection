<?php

namespace Services;

use Exception;

class Validator extends Sanitize {

    public function validate_fields($validation,$data){

        $validator_rules = array(
            'int'=> '/^\w+$/',
            'price' => '/^\d{0,8}(\.\d{1,4})?$/',
            'gram_weight' => '/^\d+g$/',
            'rating' => '/^\d{0,8}(\.\d{1,4})?$/',
            'lat_long' => '/^(-?\d+(\.\d+)?),\s*(-?\d+(\.\d+)?)$/',
            'time' => '/^\d{2}:\d{2}:\d{2}$/'
        );
        
        foreach($validation as $field_name => $validate){

            $value = $data[$field_name] ?? null;

            $nullable = $validate['nullable'] ?? false;
            $boolean =  $validate['boolean'] ?? null;
            $type = $validate['type'] ?? null;
            $regex = null;

            $max_length = $validate['max_length'] ?? null;

            $range = $validate['range'] ?? null;

            if(!is_null($boolean)){
                
               if(!is_bool($value)){
                   throw new Exception('Boolean Not Found. Value: '. $value);
               }
            }

            if($nullable && is_null($value)){
                return;
            }

            if(!is_null($range)){
                $min = $range['min'];
                $max = $range['max'];

                if($value < $min || $value > $max){
                    throw new Exception("Field($value) $field_name Exceedes Ranges");
                }
            }

            if(!is_null($max_length) && strlen($value) > $max_length){
                throw new Exception("Field $field_name Too Long. Value: $value. Length: $max_length");
            }

            if(!key_exists($field_name,$data)){
                throw new Exception("Field: $field_name Not Found");
            }

            if(!$nullable && is_null($value)){
                throw new Exception("Field: $field_name Cannot Be Null");
            }

            if(!is_null($type)){
                $regex = $validator_rules[$type] ?? null;
            }
            
            if(!is_null($regex)){
                preg_match($regex,$value,$matches);

                if(!$matches){
                    throw new Exception("$field_name($value) Failed Regex Validation: $regex");
                }
            }

        }
        
    }

}

?>