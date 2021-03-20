<?php

namespace Services;

use Exception;
use Symfony\Component\DomCrawler\Crawler;
use \Symfony\Component\HttpClient\HttpClient;

class Requests {
 
    public $logger, $config, $validator, $client;

    function __construct($config,$logger){
        $this->logger  = $logger;
        $this->config  = $config;

        $this->validator = new Validator();

        $this->client = HttpClient::create(['verify_peer' => false]);
    }

    public function request($url, $method='GET', $data=[], $headers=[], $timeout=300, $retry_attempts = null, $raw_data = false){

        $retry_config = $this->config->get('retry.request');

        $times_retried = 0;

        if($raw_data){
            $request_data = [ 'headers' => $headers, 'body' => $data];

        } else {

            if(count($data) == 0){
                $request_data = [ 'headers' => $headers,'timeout' => $timeout];
            } else {
                if($method == 'GET'){
                    $request_data = [ 'headers' => $headers,'timeout' => $timeout, 'query' => $data];
                } else {
                    $request_data = [ 'headers' => $headers,'json' => $data, 'timeout' => $timeout];
                }
            }
            
        }


        $request_send_success = false;

        $retry = $retry_attempts ?? $retry_config->attempts;
        
        $ignore_response = false;

        while($times_retried < $retry ){
            
            $wait = $retry_config->wait;

            try {

                $response = $this->client->request($method, $url,$request_data);

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

                if($retry == 1){
                    break;
                }

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
    
    // Parsing Content
    public function parse_html($content){
        return new Crawler($content);
    }

    public function parse_json($content){
        return json_decode($content);
    }

}