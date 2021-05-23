<?php

namespace Collection\Supermarkets\Asda\Services;

use Exception;

use Monolog\Logger;
use Services\ConfigService;
use Services\RequestService;

class CategoryService {

    private $endpoints, $logger;

    function __construct(ConfigService $config_service, Logger $logger){
        $this->request_service = new RequestService($config_service, $logger);
        $this->endpoints = $config_service->get('endpoints')->asda;
        $this->logger = $logger;
    }

    public function request_details($type, $item_id = null, $page_number = 1, $page_size = 60){

        $contracts = [
            'categories' => 'web/cms/taxonomy',
            'child_category' => 'web/cms/taxonomy-page',
            'promotion' => 'web/cms/link-save'
        ];

        if(!key_exists($type, $contracts)){
            throw new Exception('Unknown Request Grocery Data Type: ' . $type);
        }

        $variables = [
            'store_id' => '4565',
            'user_segments' => ['Zero_Order_Customers', 'Delivery_Pass_Older_Than_12_Months', 'Non_Baby_Customers', '1007', '1019', '1020', '1023', '1024', '1027', '1038', '1041', '1042', '1043', '1047', '1053', '1055', '1057', '1059', '1067', '1070', '1082', '1087', '1097', '1098', '1099', '1100', '1102', '1105', '1107', '1109', '1110', '1111', '1112', '1116', '1117', '1119', '1123', '1124', '1126', '1128', '1130', '1140', '1141', '1144', '1147', '1150', '1152', '1157', '1159', '1160', '1165', '1166', '1167', '1169', '1170', '1172', '1173', '1174', '1176', '1177', '1178', '1179', '1180', '1182', '1183', '1184', '1186', '1187', '1189', '1190', '1191', '1194', '1196', '1197', '1198', '1201', '1202', '1204', '1206', '1207', '1208', '1209', '1210', '1213', '1214', '1216', '1217', '1219', '1220', '1221', '1222', '1224', '1225', '1227', '1231', '1233', '1236', '1237', '1238', '1239', '1241', '1242', '1245', '1247', '1249', '1256', '1260', '1262', '1263', '1264', 'dp-false', 'wapp', 'store_4565', 'vp_XL', 'anonymous', 'checkoutOptimization', 'NAV_UI', 'T003', 'T014']
        ];


        if($type == 'child_category'){
            $this->logger->debug("Category Details For Page: $page_number");
            $variables['page_size'] = $page_size;
            $variables['page'] = $page_number;
            $variables['payload'] = [ 'page_type' => 'aisle', 'hierarchy_id' => $item_id, 'filter_query' => [],'page_meta_info' => true];
        } else {
            $variables['type'] = 'content';
            $variables['payload'] = [ 'page_id' => $item_id, 'page_type' => 'linkSave', 'page_meta_info' => true ];
        }

        $request_data = [
            'requestorigin' => 'gi',
            'contract' => $contracts[$type],
            'variables' => json_encode($variables)
        ];

        $request_data = http_build_query($request_data);

        $response = $this->request_service->request( $this->endpoints->groceries, 'POST', $request_data, ['content-type' => 'application/x-www-form-urlencoded; charset=utf-8', 'request-origin' => 'gi'], 300, null, true);
    
        $data = $this->request_service->parse_json($response);
    
        if(!property_exists($data, 'data')){
            throw new Exception('Asda Request Response Error. No Data In Response');
        }

        $required_property = [
            'categories' => 'tempo_taxonomy',
            'child_category' => 'tempo_cms_content',
            'promotion' => 'tempo_cms_content'
        ];

        $data_field = $required_property[$type];

        if(!property_exists($data->data, $data_field)){
            throw new Exception($type. ' - Required Property Not Found: '. $data_field);
        }

        return $data->data->{$data_field};

    }
    
}

?>