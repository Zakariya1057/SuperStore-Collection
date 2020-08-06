<?php

namespace Shared;

require_once __DIR__.'/../vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Loggers {

    public $logger_handler;

    function __construct()
    {
        $logger = new Logger('logger');

        $logs_directory = $this->create_day_directory();

        $this->set_push_handlers($logs_directory,$logger);
    
        $this->logger_handler = $logger;
    }

    private function create_day_directory(){

        $date = date('d-m-Y');
        $logs_directory = __DIR__."/../Logs/$date";

        if(!file_exists($logs_directory)){
            $oldmask = umask(0);
            mkdir($logs_directory, 0777);
            umask($oldmask);
        }

        return $logs_directory;

    }

    private function set_push_handlers($logs_directory, $logger){

        $logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

        $logger->pushHandler(new StreamHandler($logs_directory.'/debug.log', Logger::DEBUG,true,0777));
        $logger->pushHandler(new StreamHandler($logs_directory.'/info.log', Logger::INFO,true,0777));
        $logger->pushHandler(new StreamHandler($logs_directory.'/error.log', Logger::ERROR,true,0777));
        $logger->pushHandler(new StreamHandler($logs_directory.'/critical.log', Logger::CRITICAL,true,0777));
        $logger->pushHandler(new StreamHandler($logs_directory.'/alert.log', Logger::ALERT,true,0777));
        $logger->pushHandler(new StreamHandler($logs_directory.'/warning.log', Logger::WARNING,true,0777));
        $logger->pushHandler(new StreamHandler($logs_directory.'/notice.log', Logger::NOTICE,true,0777));

    }
}
?>