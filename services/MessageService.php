<?php

namespace Services;

use Exception;
use Models\Message\MessageModel;
use Monolog\Logger;

class MessageService {

    private $logger, $config_service, $database_service;

    private $notification_service;

    private $message_model;

    function __construct(ConfigService $config_service, Logger $logger, DatabaseService $database_service){
        $this->logger = $logger;
        $this->config_service = $config_service;
        $this->database_service = $database_service;

        $this->message_model = new MessageModel($database_service);
        $this->notification_service = new NotificationService($config_service, $logger);
    }

    public function send_message(string $type, string $text, int $from_user_id, $user){
        $user_id = $user->id;
        $user_name = $user->name;

        $this->logger->debug("Sending message to: [$user_id] $user_name");

        $this->database_service->start_transaction();

        $message = $this->message_model->create([
            'type' => $type,
            'text' => $text,

            'to_user_id' => $user_id,
            'from_user_id' => $from_user_id
        ]);

        $title = 'New ' . ucwords($type) . ' Message';

        $message = ['title' => $title, 'body' => $text];

        $data = ['type' => 'message', 'message_type' => $type, 'text' => $text];

        $this->notification_service->send_notification($user, $data, $message);

        $this->logger->debug('Message successfully sent');

        $this->database_service->commit_transaction();
        
    }

}

?>