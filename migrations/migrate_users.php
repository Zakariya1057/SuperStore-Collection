<?php

require_once './vendor/autoload.php';

use Models\Shared\UserModel;
use Services\ConfigService;
use Services\DatabaseService;
use Services\Loggers;
use Services\RequestService;

$config = new Config();

$log = new Loggers('Testing');
$logger = $log->logger_handler;

$request = new RequestService($config_service, $logger);

$database_service = new Database($config_service, $logger);

$headers = [];
$row = 0;

$user_model = new UserModel($database);

$logger->debug('------ User Migration Start ------');

$database_service->start_transaction();
$logger->debug('Lopping Through All Users');

if (($handle = fopen("users.csv", "r")) !== FALSE) {

    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {

        $num_fields = count($data);

        if($row == 0){
            $headers = $data;
        } else {
            $create_fields = [
                'store_type_id' => 2
            ];

            for($i = 0; $i < $num_fields; $i++){
                $field_content = str_replace('\'', '', trim($data[$i]));
                $field_header = trim($headers[$i]);

                $field_content = $field_content == 'NULL' ? NULL : $field_content;
                
                $create_fields[$field_header] = $field_content;
                echo "$field_header: $field_content\n";
            }

            unset($create_fields['id']);

            print_r($create_fields);

            $user_model->create($create_fields);

            echo "\n";
        }

        $row++;
    }

    fclose($handle);
}
$logger->debug('------ User Migration Complete ------');

$database_service->commit_transaction();
?>