<?php

namespace Services;

use Exception;

use Aws\S3\S3Client; 
use Aws\Credentials\Credentials;
use Monolog\Logger;

class StorageService {

    private $logger, $s3_config, $aws_credentials, $retry_conf;

    function __construct(ConfigService $config_service, Logger $logger){
        $this->logger = $logger;

        $this->s3_config = $config_service->get('s3');
        $this->retry_conf = $config_service->get('retry.s3');

        $credentials = $this->s3_config->credentials;
        $this->aws_credentials = new Credentials($credentials->key, $credentials->secret);
    }

    public function upload_s3($path, $data, $content_type = 'images'){
        $s3Client = new S3Client([
            'version' => $this->s3_config->version,
            'region'  => $this->s3_config->region,
            'credentials' => $this->aws_credentials
        ]);

        $retry_attempts = $this->retry_conf->attempts;
        $retry_wait = $this->retry_conf->wait;

        for($i = 0; $i < $retry_attempts; $i++){

            try {
                $s3Client->putObject([
                    'Bucket' => 'superstore.' . $content_type,
                    'Key' => $path,
                    'Body' => $data,
                ]);

                $this->logger->debug('Data Successfully Uploaded To AWS: ' . $path);

                break;
            } catch(Exception $e){
                $this->logger->debug('Failed To Upload Data To AWS. Error: ' . $e->getMessage());
                sleep($retry_wait);
            }

        }

        return $path;
    }


    public function store_local($path, $data, $content_type = 'images'){

    }

}

?>