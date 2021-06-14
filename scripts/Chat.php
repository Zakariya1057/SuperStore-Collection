<?php

require_once __DIR__.'/../vendor/autoload.php';

use Services\ConfigService;
use Services\DatabaseService;
use Services\LoggerService;

use Models\Message\MessageModel;
use Models\Shared\UserModel;
use Services\MessageService;

// CLI Based Chat

// 1. Select what user. Default 2

// 2. Options:
//    1. Message User
//    2. Unread messages

// 3. Respond to message.
//    1. Write message
//    1. Confirm message
//    1. Send message

$config_service = new ConfigService();

$logger_service = new LoggerService('Chat');

$logger = $logger_service->logger_handler;

$config_service->set('log_query', false);

$database_service = new DatabaseService($config_service, $logger);

$message_model = new MessageModel($database_service);
$user_model = new UserModel($database_service);

$message_service = new MessageService($config_service, $logger, $database_service);

$user_id = 2;

welcome();

while(true){

    $inputs = get_input('Command: ', true);

    $choice = $inputs[0];
    $option = $inputs[1] ?? null;

    switch($choice){
        case 'create':
            createMessage($message_service, $user_model, $user_id, $option);
            break;
        case 'new':
            newMessages($message_model, $user_id);
            break;
        case 'view':
            viewMessages($message_model, $user_id, $option);
            break;
        case 'sent':
            sentMessages($message_model, $user_id);
            break;
        case 'exit':
            exit("Goodbye ...\n\n");
            break;
        default:
            echo "Unknown Command: " . $choice;
    }

    echo "\n\n";
}

echo "\n\n\n";


function welcome(){
    echo "\n----------------------------------------------------------------------------------------------------------------------------------------\n";

    echo "\n\n";
    
    echo "\033[34m";
    
    echo "                                        
        ███████╗██╗   ██╗██████╗ ███████╗██████╗ ███████╗████████╗ ██████╗ ██████╗ ███████╗     ██████╗██╗  ██╗ █████╗ ████████╗
        ██╔════╝██║   ██║██╔══██╗██╔════╝██╔══██╗██╔════╝╚══██╔══╝██╔═══██╗██╔══██╗██╔════╝    ██╔════╝██║  ██║██╔══██╗╚══██╔══╝
        ███████╗██║   ██║██████╔╝█████╗  ██████╔╝███████╗   ██║   ██║   ██║██████╔╝█████╗      ██║     ███████║███████║   ██║   
        ╚════██║██║   ██║██╔═══╝ ██╔══╝  ██╔══██╗╚════██║   ██║   ██║   ██║██╔══██╗██╔══╝      ██║     ██╔══██║██╔══██║   ██║   
        ███████║╚██████╔╝██║     ███████╗██║  ██║███████║   ██║   ╚██████╔╝██║  ██║███████╗    ╚██████╗██║  ██║██║  ██║   ██║   
        ╚══════╝ ╚═════╝ ╚═╝     ╚══════╝╚═╝  ╚═╝╚══════╝   ╚═╝    ╚═════╝ ╚═╝  ╚═╝╚══════╝     ╚═════╝╚═╝  ╚═╝╚═╝  ╚═╝   ╚═╝                                                                                                                        
    ";
    
    echo "\033[0m";
    
    echo "\n\n";
    
    echo "----------------------------------------------------------------------------------------------------------------------------------------";
    
    echo "\n\n";
    
    echo "Welcome to superstore chat, used for communcating with users.";
    
    echo "\n\n";
    
    echo "
\033[33mAvailable Commands\033[0m:

\033[32m create \033[0m         Send message to user

\033[32m new \033[0m            View new messages

\033[32m view \033[0m           View all messages

\033[32m sent \033[0m           View all sent messages
    ";
    
    echo "\n\n";
}


function createMessage(MessageService $message_service, $user_model, int $from_user_id, int $to_user_id){
    // Send new message to user

    // 1. Confirm message to user.

    // 2. Option to write messsage

    // 3. Confirm message to user

    // 4. Send Message. Database + Notification.

    $user = $user_model->where(['id' => $to_user_id])->first();

    print_details($user);

    $input = strtolower(get_input('Confirm correct user? Y/N: '));

    if($input == 'n'){
        echo "Please go back and choose another user.\n";
        return;
    } else {

        while(true){
            $message = get_input('Write message: ');

            $input = strtolower( get_input("[Message] : $message \n\nConfirm correct message? Y/N: ") );
    
            if($input == 'y'){

                while(true){

                    $type = get_input("Choose message type?: ");
                
                    preg_match('/feedback|issue|feature|help/', $type, $correct_type_matches);
    
                    if($correct_type_matches){
                        $message_service->send_message($type, $message, $from_user_id, $user);
                        return;
                    } else {
                        echo("Incorrect Message Type. Please Try again.\n");
                    }

                }

            } 

        }

    }

}

function newMessages($message_model, $to_user_id){
    // List of messages, I haven't seen
    $messages = $message_model->join('users', 'users.id', 'messages.from_user_id')
    ->select(['messages.id', 'users.id as user_id', 'users.name', 'users.email', 'type', 'text', 'messages.created_at'])
    ->where(['to_user_id' => $to_user_id, 'message_read' => 0])
    ->get();

    $count = count($messages);

    echo "\n\033[34mAll New Messages:\033[0m $count\n";

    echo "\n\n--------------------------------------------------------\n";

    foreach($messages as $message){
        $message_model->where(['id' => $message->id])->update(['message_read' => 1]);

        print_details($message);

        echo "\n\n--------------------------------------------------------\n";
    }
}

function print_details($item){
    foreach($item as $field => $value){
        echo "\n\033[33m$field:\033[0m $value";
    }

    echo "\n";
}

function get_input($question, $split = false){
    echo "\n" . $question;

    $handle = fopen('php://stdin','r');
    $input =  trim( fgets($handle) );

    return $split ? explode(' ', $input) : $input;
}

function sentMessages($message_model, $from_user_id){
    // Output all sent messages
    $messages = $message_model->join('users', 'users.id', 'messages.from_user_id')
    ->select(['messages.id', 'users.id as user_id', 'users.name', 'users.email', 'type', 'text', 'messages.created_at'])
    ->where(['from_user_id' => $from_user_id])
    ->order_by('messages.created_at', 'ASC')
    ->get();

    $count = count($messages);

    echo "\n\033[34mSent Messages:\033[0m $count\n";

    foreach($messages as $message){
        print_details($message);
    }
}

function viewMessages($message_model, int $from_user_id, int $to_user_id){
    // View All Interactions. Back And Forth
    $messages = $message_model->join('users', 'users.id', 'messages.from_user_id')
    ->select(['messages.id', 'users.id as user_id', 'users.name', 'users.email', 'type', 'text', 'messages.created_at'])
    ->where(['to_user_id' => $to_user_id, 'from_user_id' => $from_user_id])
    ->or_where(['to_user_id' => $from_user_id, 'from_user_id' => $to_user_id])
    ->order_by('messages.created_at', 'ASC')
    ->get();

    $count = count($messages);

    echo "\n\033[34mInteraction Messages:\033[0m $count\n";

    foreach($messages as $message){
        print_details($message);
    }
}

?>