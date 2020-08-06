<?php

namespace Shared;

class Config {

    private $conf;

    function __construct(){
        $this->conf = (object)json_decode( file_get_contents(__DIR__.'/../Config/Config.json') );
    }

    public function get($field){
        return $this->conf->{$field};
    }

    public function update($field){

    }
}


?>