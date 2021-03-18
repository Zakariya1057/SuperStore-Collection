<?php

namespace Monitors;

use Interfaces\ProductInterface;
use Interfaces\PromotionInterface;
use Models\Product\ProductModel;
use Models\Shared\FavouriteModel;
use Models\Shared\GroceryListItemModel;
use Models\Shared\MonitoredProductModel;
use Models\Shared\UserModel;
use Services\Notification;
use Monolog\Logger;
use Services\Database;
use Services\Config;

class MonitorProducts {
    
    public $notification, $config, $logger, $database, $product_model;
    
    public $product_collection, $promotion_collection;

    function __construct(Config $config, Logger $logger, Database $database, ProductInterface $product_collection, PromotionInterface $promotion_collection = null){
        $this->config = $config;
        $this->logger = $logger;
        $this->database = $database;

        $this->product_model = new ProductModel($database);

        $this->product_collection = $product_collection;
        $this->promotion_collection = $promotion_collection;

        $this->notification = new Notification($config, $logger);
    }

    // Shared Monitor, check if data has changed, if so update in database.
    public function monitor_products($store_type){

        $store_name = $store_type->name;
        $store_type_id = $store_type->id;

        $products = $this->product_model
        ->select_raw(['products.*', 'count(*) as num_monitoring', 'TIMESTAMPDIFF(HOUR, `last_checked`, NOW()) as time_difference'])
        ->join('grocery_list_items', 'grocery_list_items.product_id', 'products.id')
        ->join('monitored_products', 'monitored_products.product_id', 'products.id')
        ->join('favourite_products', 'favourite_products.product_id', 'products.id')
        ->where_raw(["store_type_id = $store_type_id", 'TIMESTAMPDIFF(HOUR, `last_checked`, NOW()) > 3'])
        // ->where_raw(["store_type_id = $store_type_id"])
        ->group_by('products.id')
        ->order_by('num_monitoring')
        // ->limit(1)
        ->get();

        foreach($products as $product){

            if(is_null($product) || $product->num_monitoring == 0){
                return;
            }

            $id = $product->id;
            $name = $product->name;
            $last_checked = $product->time_difference;

            $site_product_id = $product->site_product_id;

            $this->logger->notice("------- Checking $name Product Start -----------");
            $this->logger->debug("Last Checked: $last_checked Hours Ago");
            $this->logger->debug("Updating Product: [$id] $name");


            $new_product = $this->product_collection->product_details($site_product_id, true);

            $this->check_product_change($new_product, $product);
            $this->logger->notice("------- Checking $name Product Complete --------");
        }

    }

    public function check_product_change($new_product, $old_product){
        $this->database->start_transaction();

        // If product price changes, notify relevant users.
        $update_fields = ['last_checked' => date('Y-m-d H:i:s')];

        $price_changed = $this->price_check($new_product, $old_product, $update_fields);
        $this->available_check($new_product, $old_product, $update_fields);
        $this->details_check($new_product, $old_product, $update_fields);
        $this->promotion_check($new_product, $old_product, $update_fields);

        $this->product_model->where(['id' => $old_product->id])->update($update_fields);

        $this->database->commit_transaction();

        if($price_changed){
            $this->logger->debug('Product Price Changed. Send Notification');
            $user = new UserModel();
            $user->name = 'Zakariya';
            $user->notification_token = 'e004ec30ed2277fd5a24ba8903f1d7e3ae321226d3e0fbd723ffb01865bdcbb8';

            $product_name = $new_product->name;
            $old_price = $old_product->price;
            $new_price = $new_product->price;
            $currency = $new_product->currency;

            $title = "Price Change";
            $content = "$product_name - Price Increased \n$currency$new_price";

            $this->notification->send_notification($user, ['product' => $new_product], $title, $content);
        }
    }
    

    private function available_check($new_product, $old_product, &$update_fields){
        if((int)$old_product->available != (int)$new_product->available){
            $update_fields['available'] = $new_product->available;
        }   
    }

    private function details_check($new_product, $old_product, &$update_fields){
        $check_fields = [
            'name',
            'description',
            'features',
            'dimensions',
            'promotion_id',
            'weight',
            'brand',
            'dietary_info',
            'allergen_info',
            'url',
            'storage'
        ];

        foreach($check_fields as $field){
            $old_value = $old_product->{$field};
            $new_value = $new_product->{$field};

            if($old_value != $new_value){
                $this->logger->debug( ucwords($field) . " Changed: $old_value -> $new_value");
                $update_fields[$field] = $new_value;
            }
        }
    }

    private function price_check($new_product, $old_product, &$update_fields){
        $price_changed = false;

        $old_value = $old_product->price;
        $new_value = $new_product->price;

        if($old_value != $new_value){
            $this->logger->debug("Price Changed: $old_value -> $new_value");
            $price_changed = true;
            $update_fields['price'] = $new_value;
        }

        return $price_changed;
    }

    private function promotion_check($new_product, $old_product, &$update_fields){
        if($old_product->is_on_sale && is_null($new_product->is_on_sale)){
             // Sale Expired
            $update_fields['is_on_sale'] = null;
            $update_fields['old_price'] = null;
            $update_fields['sale_ends_at'] = null;
        } else if(is_null($old_product->is_on_sale) && $new_product->is_on_sale){
            // New Sale Added
            $update_fields['is_on_sale'] = 1;
            $update_fields['old_price'] = $new_product->old_price;
            $update_fields['sale_ends_at'] = $new_product->sale_ends_at;
        }
    }

}

?>