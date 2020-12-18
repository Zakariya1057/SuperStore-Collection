<?php

namespace Stores\Asda;

use Exception;
use Models\Product\ProductModel;
use Models\Shared\FavouriteModel;
use Models\Shared\GroceryListItemModel;
use Models\Shared\MonitoredProductModel;
use Models\Shared\UserModel;
use Shared\Notification;
use Monolog\Logger;
use Shared\Config;
use Shared\Database;
use Shared\Remember;

class AsdaMonitorProducts extends Asda {

    public $notification, $product, $review, $recommended, $user_info, $product_promotions;

    function __construct(Config $config, Logger $logger, Database $database, Remember $remember=null, Notification $notification){
        parent::__construct($config,$logger,$database,$remember);
        $this->notification = $notification;
        $this->product = new ProductModel($this->database);
        $this->review = new AsdaReviews($config, $logger, $database);
        $this->recommended = new AsdaRecommended($config, $logger, $database);
        $this->user_info = new UserModel($this->database);
        $this->product_promotions = new AsdaPromotions($config, $logger, $database);
    }

    public function monitor_products(){

        $products = $this->product
        ->select_raw(['products.*', 'count(*) as num_monitoring', 'TIMESTAMPDIFF(HOUR, `last_checked`, NOW()) as time_difference'])
        ->join('grocery_list_items', 'grocery_list_items.product_id', 'products.id')
        ->join('monitored_products', 'monitored_products.product_id', 'products.id')
        ->join('favourite_products', 'favourite_products.product_id', 'products.id')
        ->where_raw(['TIMESTAMPDIFF(HOUR, `last_checked`, NOW()) > 3'])
        // ->where_raw(['products.id = 1855'])
        ->group_by('products.id')
        ->order_by('num_monitoring')
        // ->limit(1)
        ->get();

        foreach($products as $product_item){

            if(is_null($product_item) || $product_item->num_monitoring == 0){
                return;
            }

            $this->logger->notice("------- Asda Product Start -----------");
            $this->logger->debug("Last Checked: {$product_item->time_difference} Hours Ago");
            $this->logger->debug("Updating Product: [{$product_item->id}] {$product_item->name}");
            $this->check_product_change($product_item);
            $this->logger->notice("------- Asda Product Complete --------");
        }

    }

    public function check_product_change($product_item){
        
        $asda_product = new AsdaProducts($this->config, $this->logger, $this->database, null);

        $new_product = $asda_product->product_details($product_item->site_product_id, true);

        if(is_null($new_product)){
            return $this->logger->notice('No Product Details Found. Skipping for now. Will try again next run.');
        }

        $notify_user = true;
        $send_notification = false;

        $notification_type = 'product';
        $promotion_deleted = false;

        $monitor_fields = [
            'name',
            'description',
            'price',
            'weight',
            'brand',
            'dietary_info',
            'allergen_info',
            'url',
            'storage',
            'site_product_id',
        ];

        $update_fields  = ['last_checked' => date('Y-m-d H:i:s')];
        
        foreach($monitor_fields as $field){
            if($product_item->{$field} != $new_product->{$field}){
                $this->logger->notice("Product $field changed: {$product_item->{$field}} -> {$new_product->{$field}}");
                $send_notification = true;
                $notify_user = $field == 'price' ? true : false;
                $update_fields[$field] = $new_product->{$field};
            }
        }

        if(is_null($product_item->promotion_id) && !is_null($new_product->promotion)){
            // Notify User. New Promotion
            $this->logger->notice('New Product Promotion Found: '.$new_product->promotion_id);
            $notification_type = 'promotion';
            $send_notification = true;
            $update_fields['promotion_id'] = $new_product->promotion_id;
            $new_product->promotion->content = $this->product_promotions->promotion_calculator($new_product->promotion_id, $new_product->promotion->name);
        } elseif( !is_null($product_item->promotion_id) && is_null($new_product->promotion) ){
            // Notify User. Promotion Expires
            $this->logger->notice('Product promotion removed');
            $promotion_deleted = true;
            $send_notification = true;
            $update_fields['promotion_id'] = NULL;
        } 
        
        $notify_user = true;
        $this->database->start_transaction();

        // Check to see for new reviews
        // $this->review->create_review($product_item->id, $new_product->site_product_id);
        // Check to see if recommened changed
        // $this->recommended->product_recommended($product_item->id, $new_product->site_product_id);

        $this->product->where(['id' => $product_item->id])->update($update_fields);

        if($send_notification){
            $this->logger->info('Updating Product Information For: ' . $product_item->id);

            if($notification_type == 'product'){
                // Notify new promotion, price change.
                if($notify_user){
                    $this->notify_product_changes($product_item, $notification_type);
                }
            } else {
                if($$promotion_deleted){
                    $promotion = (object)['id' => (int)$product_item->promotion_id];
                    $new_product->promotion = $promotion;
                }

                $this->notify_promotion_changes($new_product->promotion, $promotion_deleted);
            }

        } else {
            $this->logger->info('Product Not Changed For: ' . $product_item->id);
        }

        $this->database->commit_transaction();

    }

    public function notify_product_changes($product_item){

        $favourite = new FavouriteModel($this->database);
        $grocery   = new GroceryListItemModel($this->database);
        $monitor   = new MonitoredProductModel($this->database);

        $favourites_users = $favourite->where(['product_id' => $product_item->id])->group_by('user_id')->get();
        $grocery_users    = $grocery->join('grocery_lists', 'grocery_lists.id', 'grocery_list_items.list_id')->where(['product_id' => $product_item->id])->group_by('user_id')->get();
        $monitored_users  = $monitor->where(['product_id' => $product_item->id])->group_by('user_id')->get();

        $unique_users = [];

        foreach(array_merge($favourites_users, $grocery_users, $monitored_users) as $user_item){

            $user_id = $user_item->user_id;
            
            if(!key_exists($user_id, $unique_users)){
                $unique_users[$user_id] = 1;

                $user = $this->user_info->where(['id' => $user_id])->get()[0] ?? null;

                if(!is_null($user) && $user->send_notifications){
                    $this->notification->notify_change($product_item, $user, 'product');
                } else {
                    throw new Exception('User not found in database: ' . $user_id);
                }

            }

        }
    }

    public function notify_promotion_changes($promotion, $delete){
        // Loop thorugh all users, notify new notification.
        $users = $this->user_info->where_raw(['send_notifications is true', 'notification_token IS NOT NULL'])->get();
        
        if( (property_exists($promotion, 'new') && $promotion->new) || !property_exists($promotion, 'new')){
            foreach($users as $user){
                $this->notification->notify_change($promotion, $user, 'promotion', $delete);
            }
        }

    }

}

?>