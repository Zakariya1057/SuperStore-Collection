<?php

namespace Supermarkets\Asda;

use Exception;
use Models\Store\StoreTypeModel;
use Monolog\Logger;
use Services\Config;
use Services\Database;
use Services\Image;
use Services\Remember;
use Services\Requests;
use Services\Sanitize;
use Supermarkets\Asda\Groceries\Groceries;
use Supermarkets\Asda\Groceries\Products\Promotions;
use Supermarkets\Asda\Groceries\Products\Recommended;
use Supermarkets\Asda\Groceries\Products\Reviews;
use Supermarkets\Asda\Services\ExcludeService;
use Supermarkets\Asda\Stores\Stores;

class Asda {

    public 
        $logger,
        $request,
        $config,
        $database,
        $endpoints,
        $env,
        $sanitize,
        $store_type_id,
        $user_id,
        $store_name,
        $store_country,
        $city,
        $exclusions,
        $remember,
        $currency,
        $image;

        // $exclude_service;
    
    function __construct(Config $config, Logger $logger, Database $database, Remember $remember=null){
        $this->request = new Requests($config,$logger);
        $this->logger  = $logger;
        $this->config  = $config;
        $this->database = $database;

        $asda_conf = $this->config->get('stores.asda');

        $this->endpoints = $this->config->get('endpoints')->asda;
        $this->env = $this->config->get('env');

        $this->sanitize = new Sanitize();

        $this->store_type_id = $asda_conf->store_type_id;
        $this->user_id = $asda_conf->user_id;
        $this->store_name = $asda_conf->name;

        $this->city = $config->get('city');
        $this->remember = $remember;

        $this->image = new Image($config,$logger,$this->request);

        $this->currency = $asda_conf->currency;
        $this->store_country = $asda_conf->country;

    }

    public function store_type(){
        $store_type = new StoreTypeModel($this->database);

        $store = $store_type->where(['id' => $this->store_type_id ])->get()[0] ?? null;

        if(is_null($store)){
            $store_type->id = $this->store_type_id;
            $store_type->name = $this->store_name;
            $store_type->user_id = $this->user_id;
            $store_type->currency = $this->currency;
            $store_type->large_logo = $this->image->save('Asda','https://dynl.mktgcdn.com/p/uxpSIwyZRALdFsUMpGERiKVVeUVlEaMMTBvKbuOZB-E/150x150.png','large','logos');
            $store_type->small_logo =  $this->image->save('Asda','https://dynl.mktgcdn.com/p/uxpSIwyZRALdFsUMpGERiKVVeUVlEaMMTBvKbuOZB-E/150x150.png','small','logos');
            $store_type->save();  
        }
 
    }

    public function recommended(){
        $recommended = new Recommended($this->config,$this->logger,$this->database,$this->remember);
        $recommended->all_recommended_products();
    }

    public function groceries(){
        $groceries = new Groceries($this->config,$this->logger,$this->database,$this->remember);
        $groceries->groceries();
    }

    public function promotions(){
        $promotions = new Promotions($this->config,$this->logger,$this->database,$this->remember);
        $promotions->promotions();
    }

    public function stores(){
        $stores = new Stores($this->config,$this->logger,$this->database,$this->remember);
        $stores->stores();
    }

    public function reviews(){
        $reviews = new Reviews($this->config,$this->logger,$this->database,$this->remember);
        $reviews->reviews();
    }



    public function request_details($type, $item_id = null){

        $contracts = [
            'categories' => 'web/cms/taxonomy',
            'child_category' => 'web/cms/taxonomy-page'
        ];

        if(!key_exists($type, $contracts)){
            throw new Exception('Unknown Request Grocery Data Type: ' . $type);
        }

        $variables = [
            'ship_date' => 1616025600000,
            'store_id' => '4565',
            'special_offer' => false,
            'user_segments' => ['Zero_Order_Customers', 'Delivery_Pass_Older_Than_12_Months', 'Non_Baby_Customers', '1007', '1019', '1020', '1023', '1024', '1027', '1038', '1041', '1042', '1043', '1047', '1053', '1055', '1057', '1059', '1067', '1070', '1082', '1087', '1097', '1098', '1099', '1100', '1102', '1105', '1107', '1109', '1110', '1111', '1112', '1116', '1117', '1119', '1123', '1124', '1126', '1128', '1130', '1140', '1141', '1144', '1147', '1150', '1152', '1157', '1159', '1160', '1165', '1166', '1167', '1169', '1170', '1172', '1173', '1174', '1176', '1177', '1178', '1179', '1180', '1182', '1183', '1184', '1186', '1187', '1189', '1190', '1191', '1194', '1196', '1197', '1198', '1201', '1202', '1204', '1206', '1207', '1208', '1209', '1210', '1213', '1214', '1216', '1217', '1219', '1220', '1221', '1222', '1224', '1225', '1227', '1231', '1233', '1236', '1237', '1238', '1239', '1241', '1242', '1245', '1247', '1249', '1256', '1260', '1262', '1263', '1264', 'dp-false', 'wapp', 'store_4565', 'vp_XL', 'anonymous', 'checkoutOptimization', 'NAV_UI', 'T003', 'T014']
        ];


        if($type == 'child_category'){
            $variables['payload'] = [ 'page_type' => 'aisle', 'hierarchy_id' => $item_id, 'filter_query' => [],'page_meta_info' => true];
        }

        $request_data = [
            'requestorigin' => 'gi',
            'contract' => $contracts[$type],
            'variables' => json_encode($variables)
        ];

        $request_data = http_build_query($request_data);

        $response = $this->request->request( $this->endpoints->groceries, 'POST', $request_data, ['content-type' => 'application/x-www-form-urlencoded; charset=utf-8', 'request-origin' => 'gi'], 300, null, true);
    
        $data = $this->request->parse_json($response);
    

        if(!property_exists($data, 'data')){
            throw new Exception('Asda Request Response Error. No Data In Response');
        }

        $required_property = [
            'categories' => 'tempo_taxonomy',
            'child_category' => 'tempo_cms_content'
        ];

        $data_field = $required_property[$type];

        if(!property_exists($data->data, $data_field)){
            throw new Exception($type. ' - Required Property Not Found: '. $data_field);
        }

        return $data->data->{$data_field};

    }
}

?>