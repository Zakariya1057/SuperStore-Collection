<?php

namespace Shared;

class Sanitize {

    public function sanitize_fields($data){
        $data = (array)$data;

        foreach($data as $key => $value){
            if(is_array($value)){
                $data[$key] = $this->sanitize_fields($value);
            } else {
                $data[$key] = $this->sanitize_field($value);
            }
        }

        return $data;
    }

    public function sanitize_field($string){
        
        if(is_null($string)){
            return;
        }

        $string = str_replace('\n', "\n", $string);
        $string = strip_tags($string);
        $string = preg_replace( "/\r/", "", $string);
        $string = htmlentities($string, ENT_QUOTES,'UTF-8',false);
        
        return $string;
    }
    
    public function removeCurrency($price){
        return str_replace('£','',$price);
    }
}

?>