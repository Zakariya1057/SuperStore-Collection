<?php

namespace Services;

use Exception;
use Monolog\Logger;

class NotificationService {

    private $logger;
    private $file_path, $pass_phrase, $url;

    function __construct(ConfigService $config_service, Logger $logger){
        $this->logger = $logger;

        $this->file_path = __DIR__ .'/../'. $config_service->get('notification.file_path');
        $this->pass_phrase = $config_service->get('notification.pass_phrase');
        $this->url = $config_service->get('notification.url');
    }

    // Send notification to user phone.
    function send_notification($user, $data, $message){
        $notification_token = $user->notification_token;

        $this->logger->info("Sending Notification To: [{$user->id}] {$user->name}");
        $this->logger->info("Notification Token: $notification_token");
        
        $ctx = stream_context_create();
        stream_context_set_option($ctx, 'ssl', 'local_cert', $this->file_path);
        stream_context_set_option($ctx, 'ssl', 'passphrase', $this->pass_phrase);
        
        $fp = stream_socket_client('ssl://'. $this->url , $err, $errstr, 60, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $ctx);

        // Create the payload body
        $body['aps'] = array(
            'badge' => +1,
            'alert' => $message,
            'sound' => 'default',
            'data' => (array)$data
        );
        
        $payload = json_encode($body);
        
        // Build the binary notification
        $msg = chr(0) . pack('n', 32) . pack('H*', $notification_token) . pack('n', strlen($payload)) . $payload;
        
        // Send it to the server
        $result = fwrite($fp, $msg, strlen($msg));
        
        if (!$result){
            throw new Exception('Failed To Send User Notification');
        } else {
            $this->logger->notice('Notification Sent Successfully');
        }

        fclose($fp);

    }

}