<?php

namespace Shared;

use Exception;
use Symfony\Component\DomCrawler\Crawler;
use \Symfony\Component\HttpClient\HttpClient;

// Used for sending GET/POST Requests. Retrying And Logging
class Requests {
 
    public $logger,$config,$validator;

    function __construct($config,$logger){
        $this->logger  = $logger;
        $this->config  = $config;
        $this->validator = new Validator();
    }

    public function request($url, $method='GET',$data=[],$headers=[],$timeout=300){

        $client = HttpClient::create();
        
        $retry_config = $this->config->get('retry.request');

        $times_retried = 0;

        if(count($data) == 0){
            $request_data = [ 'headers' => $headers,'timeout' => $timeout];
        } else {

            if($method == "GET"){

                $request_data = [ 'headers' => $headers,'timeout' => $timeout, 'query' => $data];
            } else {
                $request_data = [ 'headers' => $headers,'json' => $data, 'timeout' => $timeout];
            }
           
        }

        $request_send_success = false;

        $retry =  $retry_config->attempts;
        
        $ignore_response = false;

        while($times_retried < $retry ){
            
            $wait = $retry_config->wait;

            try {

                $response = $client->request($method, $url,$request_data);

                $this->logger->debug("$method REQUEST: $url");

                $statusCode = $response->getStatusCode();
                
                $content = $response->getContent(false);
                
                $this->logger->debug('Response Status Code: '.$statusCode);
                
                if ($statusCode == 200 ){    
                    $request_send_success = true;
                    break;
                } else {
                    throw new Exception("$statusCode Error: ".$content);
                } 

            } catch(Exception $e){
                $this->logger->error('Request Error: '.$e->getMessage() );
                $this->logger->error("Waiting $wait Seconds");
                sleep( $wait );
            }

            $times_retried++;
        }

        
        if($request_send_success || $ignore_response){
            return $content;
        } else {
            $this->logger->error('--- Failed To Send Request. ---');
            $this->logger->error("--- URL: $url ---");
            $this->logger->error("--- Method: $method ---");

            throw new Exception('Failed To Send Request: '.$url);
        }

    }
    
    public function parse_html($content){
        return new Crawler( $content );
    }

    public function parse_json($content){
        return json_decode($content);
    }

}