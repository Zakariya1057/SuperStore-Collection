<?php

namespace Shared;

use Exception;

class Remember {

    private $config,$logger;

    private $error_file,$error_message,$error_stack;

    private $grand_parent_category_index,$parent_category_index,$child_category_index,$product_index, $line_number;

    public $site_name;

    private $file_location;

    function __construct($config,$logger) {
        $this->logger = $logger;
        $this->config = $config;

        $this->file_location = __DIR__."/../Error/Error.json";
    }

    public function get($name){
        //Get Details About Last Run
        $value = $this->{$name};
        // $this->logger->debug("Getting $name: $value");
        return $value;
    }

    public function set($name,$index){
        //Save Details After Failure
        $this->{$name} = $index;
        $this->logger->debug("Setting $name: $index");
        $this->save_data();
    }

    public function set_error($error_message,$error_file,$error_stack,$line_number){
        $this->error_file = $error_file;
        $this->error_message = $error_message;
        $this->error_stack = $error_stack;
        $this->line_number = $line_number;
    }

    public function retrieve_data(){
        //Retrieve data from config and store in params
        $data = json_decode( file_get_contents($this->file_location) );

        $details = $data->sites->{$this->site_name};

        $this->grand_parent_category_index = $details->grand_parent_index;
        $this->parent_category_index = $details->parent_category_index;
        $this->child_category_index = $details->child_category_index;
        $this->product_index = $details->product_index;
    }

    public function save_data(){
        //Save Details To File
        $data = [];

        $error_details = [
            'message' => $this->error_message,
            'file' => $this->error_file,
            'line_number' => $this->line_number,
            // 'stack_trace' => $this->error_stack,
        ];

        $last_details = [
            $this->site_name => [
                "grand_parent_index" =>  $this->grand_parent_category_index,
                "parent_category_index" => $this->parent_category_index,
                "child_category_index" => $this->child_category_index,
                "product_index" => $this->product_index
            ]

        ];

        $data['sites'] = $last_details;
        $data['error'] = $error_details;

        file_put_contents($this->file_location,json_encode($data));
    }

}

?>