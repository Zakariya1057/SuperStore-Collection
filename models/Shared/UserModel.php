<?php

namespace Models\Shared;

use Models\Model;
use Services\DatabaseService;

class UserModel extends Model {

    public 
        $name,
        $email,
        $password,
        $identifier,
        $send_notifications,
        $notification_token,
        $remember_token,
        $company_id,
        $token_sent_at,
        $logged_in_at,
        $logged_out_at;
    
    function __construct(DatabaseService $database_service=null){

        parent::__construct($database_service);

        $this->table('users');

        $fields = [
            'company_id' => [],
            'name' => [],
            'email' => [],
            'password' => [],
            'identifier' => [
                'nullable' => true
            ],
            'send_notifications' => [],
            'notification_token' => [
                'nullable' => true
            ],
            'remember_token' => [
                'nullable' => true
            ],
            'token_sent_at' => [
                'nullable' => true
            ],
            'logged_in_at' => [],
            'logged_out_at' => [
                'nullable' => true
            ],
        ];

        $this->fields($fields);

    }

}

?>