<?php

namespace Services;

use Exception;
use Aws\S3\S3Client; 
use Aws\Credentials\Credentials;

class Image {

    private $logger, $img_config, $aws_credentials, $request;

    function __construct($config,$logger,Requests $request){
        $this->logger = $logger;

        $this->img_config = $config->get('images');
        $this->host = $this->img_config->host;

        $credentials = $this->img_config->aws->credentials;
        $this->aws_credentials = new \Aws\Credentials\Credentials($credentials->key, $credentials->secret);

        $this->request = $request;
    }

    public function save($name, $image_url,$size='',$type='products', $store='Asda'){

        $store = str_replace(' ', '_', $store);

        $name = "{$name}_{$size}.jpg";
        $type = $this->type_directories($type);
        $image = $this->get_image($image_url);

        $this->logger->debug("Image URL: $image_url");

        if(is_null($image)){
            return;
        }

        if($this->img_config->saving_location == 'local'){
            $this->logger->debug('Saving Images On Local Machine');
            $file_location = $this->store_local($name, $image,$type,$store);
        } else {
            $this->logger->debug('Saving Images On AWS S3 Bucket');
            $file_location = $this->store_aws($name, $image,$type,$store);
        }

        $this->logger->debug("Saving $type Images To $file_location");
        
        return $file_location;
    }

    private function store_local($name, $image, $type, $store){
        //Store On Local Server

        $dir = __DIR__ .'/../' . $this->img_config->local->location;

        if(!file_exists($dir . '/' . $type)){
            $this->logger->debug('Creating Image Directory: ' . $dir . '/' . $type);
            mkdir($dir . '/' . $type, 0777, true);
        }

        $relative_file_location = "$type/{$store}_{$name}";

        $file_location = "$dir/$relative_file_location";

        if(!file_put_contents($file_location, $image)){
            throw new Exception("Failed To Save Image: $file_location");
        }

        return $relative_file_location;
    }

    private function store_aws($name, $image, $type, $store){
        // Store On S3 Bucket
        $aws = $this->img_config->aws;

        $file_location = "$type/{$store}_{$name}";

        //Create a S3Client
        $s3Client = new S3Client([
            'version' => $aws->version,
            'region'  => $aws->region,
            'credentials' => $this->aws_credentials
        ]);

        $s3Client->putObject([
            'Bucket' => 'superstore.images',
            'Key' => $file_location,
            'Body' => $image,
        ]);

        return $file_location;
    }

    private function type_directories($type){
        $locations = $this->img_config->type_locations;
        
        strtolower($type);

        if(property_exists($locations, $type)){
            return $locations->{$type};
        } else {
            throw new Exception('Unknown Image Saving Option: '.$type);
        }
    }

    private function get_image($url){
        try {
            return $this->request->request($url, 'GET', [], [], 300, 1);
        } catch (Exception $e){
            $this->logger->error('Failed To Get Image: '. $e->getMessage());
            return;
        }
    }

}

?>