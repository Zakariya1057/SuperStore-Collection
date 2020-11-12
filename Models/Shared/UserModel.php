<?php

namespace Models\Shared;

use Models\Model;

class UserModel extends Model {

    public $id, $name, $email, $password, $identifier, $notification_token;
    
    function __construct($database=null){

        parent::__construct($database);

        $this->table('users');

        $fields = [
            'product_id' => [
                'type' => 'int'
            ],

            'user_id' => [
                'type' => 'int'
            ],
        ];

        $this->fields($fields);

    }

}

?>