<?php

namespace Shared;

class Sanitize {

    public function sanitizeFields($data){
        $data = (array)$data;

        foreach($data as $key => $value){
            if(is_array($value)){
                $data[$key] = $this->sanitizeFields($value);
            } else {
                $data[$key] = $this->sanitizeField($value);
            }
        }

        return $data;
    }

    public function sanitizeField($string){
        
        if(!$string){
            return;
        }

        $string = strip_tags($string);
        $string = preg_replace( "/\r/", "", $string);
        $string = htmlspecialchars($string, ENT_QUOTES,'ISO-8859-1', false);
        
        return $string;
    }
}

?>