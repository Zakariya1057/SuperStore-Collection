<?php
 
namespace Models\Message;

use Models\Model;
use Services\DatabaseService;

class MessageModel extends Model {

    public $type, $text, $message_read, $from_user_id, $to_user_id;
    
    function __construct(DatabaseService $database_service=null){

        parent::__construct($database_service);

        $this->table('messages');

        $fields = [
            'text' => [],
            'type' => [],

            'message_read' => [
                'nullable' => true
            ],

            'from_user_id' => [],
            'to_user_id' => [],
        ];

        $this->fields($fields);

    }

}

?>