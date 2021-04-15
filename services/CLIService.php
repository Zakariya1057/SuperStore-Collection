<?php

namespace Services;

require_once __DIR__.'/../vendor/autoload.php';

use Exception;
use splitbrain\phpcli\CLI;
use splitbrain\phpcli\Options;

class CLIService extends CLI {

    private $store_type;

    // register options and arguments
    protected function setup(Options $options)
    {
        $options->setHelp('SuperStore grocery stores scraper.');
        $options->registerOption('store', 'Store type to scrape content from.', 's', 'store_type');
        $options->registerOption('version', 'print version', 'v');
    }

    // implement your code
    protected function main(Options $options)
    {
        if ($store_type = $options->getOpt('store')) {
            $this->store_type = $store_type;
        } else {
            echo $options->help();
            exit();
        }
    }

    public function get_store_type(): String {
        return $this->store_type;
    }


}


?>