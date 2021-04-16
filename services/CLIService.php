<?php

namespace Services;

require_once __DIR__.'/../vendor/autoload.php';

use Exception;
use splitbrain\phpcli\CLI;
use splitbrain\phpcli\Options;

class CLIService extends CLI {

    private $store_type, $monitor_type;

    // register options and arguments
    protected function setup(Options $options)
    {
        $options->setHelp('SuperStore grocery stores scraper.');
        $options->registerOption('store', 'Store type to scrape content from.', 's', 'store_type');
        $options->registerOption('type', 'Monitor type, store or product', 't', 'monitor_type');
        $options->registerOption('version', 'print version', 'v');
    }

    // implement your code
    protected function main(Options $options)
    {
        if ($store_type = $options->getOpt('store')) {
            $this->info("Store Type: $store_type");
            $this->store_type = $store_type;
        } 
        
        if ($monitor_type = $options->getOpt('type')){
            $this->info("Monitor Type: $monitor_type");
            $this->monitor_type = $monitor_type;
        }
    }

    public function get_store_type(): String {
        return $this->store_type;
    }

    public function get_monitor_type(): String {
        return $this->monitor_type;
    }


}


?>