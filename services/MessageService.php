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

        $message_id = $this->message_model->create([
            'type' => $type,
            'text' => $text,

            'to_user_id' => $user_id,
            'from_user_id' => $from_user_id
        ]);

        $title = 'New ' . ucwords($type) . ' Message';

        $message = ['title' => $title, 'body' => $text];

        $inserted_message = $this->message_model->where(['id' => $message_id])->first();

        $this->format_message($inserted_message);

        print_r($inserted_message);

        $data = ['type' => 'message', 'message' => $inserted_message];

        $this->notification_service->send_notification($user, $data, $message);

        $this->logger->debug('Message successfully sent');

        $this->database_service->commit_transaction();
        
    }

    private function format_message(&$message){
        $message->id = (int)$message->id;

        $message->created_at = (string) date('Y-m-d H:i:s', strtotime( $message->created_at ));
        $message->updated_at = (string) date('Y-m-d H:i:s', strtotime( $message->updated_at ));

        return $message;
    }

}

?>