<?php

namespace Shared;

class Config {

    private $conf;

    function __construct(){
        $this->conf = (object)json_decode( file_get_contents(__DIR__.'/../Config/Config.json') );
    }

    public function get($field){
        $field_list = explode('.',$field) ?? [$field];

        $details = $this->conf;

        foreach($field_list as $field_name){
            $details = $details->{$field_name};
        }

        return $details;
    }

    public function set($field,$value){
        $field_list = explode('.',$field) ?? [$field];

        $details = $this->conf;

        foreach($field_list as $field_name){
            $details->{$field_name} = $value;  
        }
    }

}


?>