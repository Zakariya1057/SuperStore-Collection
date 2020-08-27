<?php

namespace Shared;

use Exception;
use Models\ScriptHistory\ScriptHistory;

class Remember {

    private $config,$logger;

    public $store_type_id;

    private $history;

    private $error_file,$error_message,$error_stack;
    private $grand_parent_category_index,$parent_category_index,$child_category_index,$product_index, $error_line_number;

    function __construct($config,$logger,$database) {
        $this->logger = $logger;
        $this->config = $config;

        $this->history = new ScriptHistory($database);
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
        $this->history->where(['store_type_id' => $this->store_type_id])->update([$name => $index]);
    }

    public function set_error($error_message,$error_file,$error_stack,$line_number){
        $this->error_file = $error_file;
        $this->error_message = $error_message;
        $this->error_stack = $error_stack;
        $this->line_number = $line_number;
    }

    public function retrieve_data(){
        //Retrieve data from config and store in params

        if($this->config->get('continue')){

            $details = $this->history->where(['store_type_id' => $this->store_type_id])->get();

            if(!$details){
                $this->logger->debug('No Script History Found For Site. Creating One');
                $this->history->create(['store_type_id' => $this->store_type_id]);
            } else {
                $this->grand_parent_category_index = $details->grand_parent_category_index;
                $this->parent_category_index = $details->parent_category_index;
                $this->child_category_index = $details->child_category_index;
                $this->product_index = $details->product_index;
            }

        }

    }

    public function save_data(){
        //Saving Details To Database
        $this->history->where(['store_type_id' => $this->store_type_id])->update([
            'grand_parent_category_index' => $this->grand_parent_category_index,
            'parent_category_index' => $this->parent_category_index,
            'child_category_index' => $this->child_category_index,
            'product_index' => $this->product_index,

            'error_message' => $this->error_message,
            'error_line_number' => $this->error_line_number,
            'error_file' => $this->error_file
        ]);

    }


}

?>