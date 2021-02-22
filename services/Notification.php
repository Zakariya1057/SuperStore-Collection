<?php

namespace Services;

use Exception;
use Models\Product\ProductModel;

class Notification {

    private $logger, $config;
    private $file_path, $pass_phrase, $url;

    function __construct($config,$logger){
        $this->config = $config;
        $this->logger = $logger;

        $this->file_path = __DIR__ .'/../'. $config->get('notification.file_path');
        $this->pass_phrase = $config->get('notification.pass_phrase');
        $this->url = $config->get('notification.url');

        $this->config = $config;
    }

    // Send notification to user phone.
    function notify_change($data, $user, $notification_type, $delete = false){
        $notification_token = $user->notification_token;
        
        $data = (object)$data;

        $this->logger->info("Sending Notification To: [{$user->id}] {$user->name}");
        $this->logger->info("Notification Token: $notification_token");
    
        $message = ['title' => null, 'body' => null];
        $content = [];

        if(!$delete){
            
            $this->logger->info("$notification_type Notify: {$data->name}");

            if($notification_type == 'product'){
                $message = ['title' => 'Price Changed', 'body' => "£{$data->price} - {$data->name}"];
                $content = [
                    'id' => (int)$data->id,
                    'price' => (double)$data->price
                ];
            } else {
                $message = ['title' => "{$data->category} Sale", 'body' => $data->name];
                $content = (array)$data->content;
                $content['id'] = (int)$data->promotion_id;
                $content['quantity'] = $content['quantity'] ?? 0;
                $content['for_quantity'] = $content['for_quantity'] ?? 0;
            }
        } else {
            $this->logger->info("Delete $notification_type Notification: {$data->id}");
            $content = (array)$data;
        }
        
        $ctx = stream_context_create();
        stream_context_set_option($ctx, 'ssl', 'local_cert', $this->file_path);
        stream_context_set_option($ctx, 'ssl', 'passphrase', $this->pass_phrase);
        
        $fp = stream_socket_client('ssl://'. $this->url , $err, $errstr, 60, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $ctx);

        $content['type'] = $notification_type;
        $content['delete'] = (bool)$delete;

        // Create the payload body
        $body['aps'] = array(
            'badge' => +1,
            'alert' => $message,
            'sound' => 'default',
            'data' => (array)$content
        );

        if($delete){
            unset($body['aps']['alert']);
            unset($body['aps']['sound']);
            $body['aps']['content-available'] = 1;
        }
        
        $payload = json_encode($body);
        
        // Build the binary notification
        $msg = chr(0) . pack('n', 32) . pack('H*', $notification_token) . pack('n', strlen($payload)) . $payload;
        
        // Send it to the server
        $result = fwrite($fp, $msg, strlen($msg));
        
        if (!$result){
            throw new Exception('Failed To Send User Notification');
        } else {
            $this->logger->notice('Notification Sent Successfully: '.$message['title']);
        }

        fclose($fp);

    }

}