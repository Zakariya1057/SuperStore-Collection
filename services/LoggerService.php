<?php

namespace Services;

require_once __DIR__.'/../vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class LoggerService {

    public $logger_handler;

    function __construct(String $type, String $sub_type = null)
    {
        $logger = new Logger('logger');

        $logs_directory = $this->create_day_directory($type, $sub_type);

        $this->set_push_handlers($logs_directory,$logger);
    
        $this->logger_handler = $logger;
    }

    private function create_day_directory(String $type, $sub_type){

        $date = date('d-m-Y');

        $type = str_replace(' ', '_', ucwords($type));
        $sub_type = str_replace(' ', '_', ucwords($sub_type));

        $logs_directory = __DIR__."/../logs/$type/";
        // $logs_directory = __DIR__."/../logs/$type/$date";

        if(!is_null($sub_type)){
            $logs_directory .= "$sub_type/";
        }

        $logs_directory .= $date;

        if(!file_exists($logs_directory)){
            $oldmask = umask(0);
            mkdir($logs_directory, 0777, true);
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