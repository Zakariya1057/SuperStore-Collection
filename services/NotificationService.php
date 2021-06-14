<?php

namespace Services;

use Firebase\JWT\JWT;
use Monolog\Logger;

class NotificationService {

    private $logger, $request;
    
    private $bundle_id, $key, $key_id, $url;

    function __construct(ConfigService $config_service, Logger $logger){
        $this->logger = $logger;

        $notification_config = $config_service->get('notification');

        $this->key = file_get_contents( __DIR__ .'/../'. $notification_config->file_path);
        $this->url = $notification_config->url;

        $this->bundle_id = $notification_config->bundle_id;
        $this->key_id = $notification_config->key_id;

        $this->request = new RequestService($config_service, $logger);
    }

    // Send notification to user phone.
    function send_notification($user, $data, $message){
        // $notification_token = $user->notification_token;
        $device_token = 'bfebc29a163990bd3ab029e7d94ae37e85bc5264e05fbfaa43681f2ee8cfd03e';

        $this->logger->info("Sending Notification To: [{$user->id}] {$user->name}");
        $this->logger->info("Device Token: $device_token");

        $payload = $this->generate_payload($message, $data);
        
        $headers = $this->generate_headers();
        
        $url = $this->url . $device_token;

        $this->request->request($url, 'POST', $payload, $headers);

        $this->logger->notice('Notification Sent Successfully');

    }

    private function generate_token(){
        $payload = array(
            "kid" => "Q9JS5DX954",
            "iss" => "5FCM3X5N8A",
            "alg" => "ES256",
            "iat" => time()
        );

        return (string) JWT::encode($payload, $this->key, 'ES256', $this->key_id);
    }

    private function generate_headers(){
        $token = $this->generate_token();

        $this->logger->debug('Notification Token: ' . $token);

        return [
            "Authorization" =>  "Bearer $token", 
            "apns-topic" => $this->bundle_id, 
            "apns-expiration" =>  "0",
            "apns-priority" => "10",
        ];
    }

    private function generate_payload($message, $data){
        return [ 
            'aps' => [
                'badge' => +1,
                'alert' => $message,
                'sound' => 'default',
                'data' => (array)$data
            ]
        ];
    }

}