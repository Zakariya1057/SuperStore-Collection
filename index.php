<?php

    ini_set('memory_limit', '-1');

    require_once __DIR__.'/vendor/autoload.php';

    // TODO - Product Update Daily. Reviews, New Suggestions, Price Change, Promotions
    // TODO - Use Parent Category To See If Haram: 1000017525663 - If meat in parent category
    // TODO - New Promotions

    use Shared\Config;
    use Shared\Loggers;
    use Shared\Database;
    use Shared\Remember;
    use Stores\Asda\Asda;

    $config = new Config();
    $log = new Loggers();
    
    $logger = $log->logger_handler;

    $database = new Database($config,$logger);

    $remember = new Remember($config,$logger,$database);

    $logger->notice("---------------------------- Script Start ----------------------------");

    $asda_conf = $config->get('asda');

    if($config->get('env') == 'dev'){
        $logger->notice('Running In Development Environment.');
    } else {
        $logger->notice('Running In Live Environment.');
    }

    try {

        if($asda_conf->run){

            $remember->store_type_id = $asda_conf->store_type_id;
            $remember->retrieve_data();

            $logger->notice("----------  Asda Scraping Start ----------");
            $asda = new Asda($config,$logger,$database,$remember);
        
            if($asda_conf->stores){
                //Get all stores in given city
                $asda->stores();
            }
        
            if($asda_conf->groceries){
                //Get all product sold on site
                $asda->groceries();
            }
        
            //Searches For new promotions.
            if($asda_conf->promotions){
                //Get new promotions. Update old ones
                $asda->promotions();
            }
        
            if($asda_conf->recommended){
                //Get all similar Products.
                $asda->recommended();
            }

            if($asda_conf->reviews){
                $asda->reviews();
            }

            $logger->notice("---------- Asda Scraping Complete ---------- ");
        }

    } catch(Exception $e){
        //Save failure error
        //Exit Script

        $error_message = $e->getMessage();
        $error_file = $e->getFile();
        $error_stack = $e->getTraceAsString();
        $line_number = $e->getLine();

        $remember->set_error($error_message,$error_file,$e->getTrace(),$line_number);

        $logger->error('Error Occured Exiting Script');
        $logger->error('Message: ' . $error_message);
        $logger->error('File: ' . $error_file);
        $logger->error('Error Stack: ' .$error_stack);

        $remember->save_data();
        
        throw new Exception($e);
    }

    $logger->notice("---------------------------- Script Complete ----------------------------");

?>