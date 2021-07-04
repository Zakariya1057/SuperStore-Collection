<?php

namespace Services;

use Exception;
use Models\ScriptHistory\ScriptHistory;
use Monolog\Logger;

class RememberService {

    private $logger;

    public $company_id;

    private $history;

    private $grand_parent_category_index, $parent_category_index, $child_category_index, $product_index;

    private static $savedHistory;

    function __construct(ConfigService $config_service,Logger $logger, DatabaseService $database_service) {
        $this->logger = $logger;
        $this->config_service = $config_service;
        $this->history = new ScriptHistory($database_service);
    }

    public function get($name){
        $value = $this->{$name};
        return $value;
    }

    public function set($name,$index){
        //Save Details After Failure
        $this->{$name} = $index;
        $this->logger->debug("Setting $name: $index");
        $this->history->where(['company_id' => $this->company_id])->update([$name => $index]);
    }

    public function retrieve_data(){
        //Retrieve data from config and store in params

        if($this->config_service->get('continue')){

            if(is_null(self::$savedHistory)){
                $details = $this->history->where(['company_id' => $this->company_id])->first();
                self::$savedHistory = $details;
            } else {
                $details = self::$savedHistory;
            }
            
            if(is_null($details)){
                $this->logger->debug('No Script History Found For Site. Creating One');
                $history = $this->history;
                $history->company_id = $this->company_id;
                $history->insert_ignore = 1;
                $history->save();
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
        $this->history->where(['company_id' => $this->company_id])->update([
            'grand_parent_category_index' => $this->grand_parent_category_index,
            'parent_category_index' => $this->parent_category_index,
            'child_category_index' => $this->child_category_index,
            'product_index' => $this->product_index
        ]);

    }


}

?>