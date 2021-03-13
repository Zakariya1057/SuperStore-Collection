<?php
    require_once './vendor/autoload.php';

    use Services\Config;
    use Services\Loggers;
    use Services\Requests;
    use Services\Sanitize;

    $config = new Config();

    $log = new Loggers();
    $logger = $log->logger_handler;

    $request = new Requests($config,$logger);

    $response = $request->request('https://www.realcanadiansuperstore.ca/api/product/20817066001_KG?pickupLocationId=1080&isInventoryInfoRequired=false','GET',[],['Site-Banner' => 'superstore']);
    print_r( $request->parse_json($response) );
?>