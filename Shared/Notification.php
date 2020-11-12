<?php

namespace Shared;

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
    function notify_change($product, $user){
        $notification_token = $user->notification_token;

        $product_name = html_entity_decode($product->name, ENT_QUOTES);
        $product_id = (int)$product->id;

        $this->logger->info("Sending Notification To: [{$user->id}] {$user->name}");
        $this->logger->info("Notification Token: $notification_token");
        $this->logger->info("Product Notify: $product_name");

        $message = ['title' => 'Price Changed', 'body' => "£{$product->price} - {$product_name}"];

        $ctx = stream_context_create();
        stream_context_set_option($ctx, 'ssl', 'local_cert', $this->file_path);
        stream_context_set_option($ctx, 'ssl', 'passphrase', $this->pass_phrase);
        
        $fp = stream_socket_client('ssl://'. $this->url , $err, $errstr, 60, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $ctx);

        // Create the payload body
        $body['aps'] = array(
            'badge' => +1,
            'alert' => $message,
            'sound' => 'default',
            'data' => [
                'product_id' => $product_id,
                'name' => $product_name
            ]
        );
        
        $payload = json_encode($body);
        
        // Build the binary notification
        $msg = chr(0) . pack('n', 32) . pack('H*', $notification_token) . pack('n', strlen($payload)) . $payload;
        
        // Send it to the server
        $result = fwrite($fp, $msg, strlen($msg));
        
        if (!$result){
            throw new Exception('Failed To Sent User Notification');
        } else {
            $this->logger->notice('Notification Sent Successfully: '.$message['title']);
        }

        fclose($fp);

    }

}