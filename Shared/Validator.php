<?php

namespace Shared;

use Exception;

class Validator extends Sanitize {

    public function validate_fields($validation,$data){
        // Use field type to see if its valid.
        // For product price, check if valid price and if not null

        $validator_rules = array(
            "int" => "/^\w+$/"
        );
        
        foreach($validation as $field_name => $validate){

            $nullable = $validate['nullable'] ?? false;
            $type = $validate['type'] ?? null;

            if($type){
                $regex = $validator_rules[$type] ?? null;
            }
            
            if(!key_exists($field_name,$data)){
                throw new Exception("Field $field_name Not Found");
            }

            $field = $data[$field_name];

            if(!$nullable && is_null($field)){
                throw new Exception("$field_name Cannot Be Null");
            }

            if(isset($regex)){
                preg_match($regex,$field,$matches);

                if(!$matches){
                    throw new Exception("$field_name Failed Regex Validation: $regex");
                }
            }

        }
        
    }

    public function validate_url($url){
        preg_match('/^([a-z][a-z0-9\*\-\.]*):\/\/(?:(?:(?:[\w\.\-\+!$&\'\(\)*\+,;=]|%[0-9a-f]{2})+:)*(?:[\w\.\-\+%!$&\'\(\)*\+,;=]|%[0-9a-f]{2})+@)?(?:(?:[a-z0-9\-\.]|%[0-9a-f]{2})+|(?:\[(?:[0-9a-f]{0,4}:)*(?:[0-9a-f]{0,4})\]))(?::[0-9]+)?(?:[\/|\?](?:[\w#!:\.\?\+=&@!$\'~*,;\/\(\)\[\]\-]|%[0-9a-f]{2})*)?$/',$url,$matches);
        if($matches){
            return true;
        } else {
            return false;
        }
    }

}

?>