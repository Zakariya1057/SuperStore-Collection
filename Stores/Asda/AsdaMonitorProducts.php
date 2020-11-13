<?php

namespace Stores\Asda;

use Exception;
use Models\Product\ProductModel;
use Models\Shared\FavouriteModel;
use Models\Shared\GroceryListItemModel;
use Models\Shared\MonitoredProductModel;
use Models\Shared\UserModel;

class AsdaMonitorProducts extends Asda {

    public $notification, $product, $review, $recommended;

    function __construct($config,$logger,$database,$remember, $notification){
        parent::__construct($config,$logger,$database,$remember);
        $this->notification = $notification;
        $this->product = new ProductModel($this->database);
        $this->review = new AsdaReviews($config, $logger, $database, null);
        $this->recommended = new AsdaRecommended($config, $logger, $database, null);
    }

    public function monitor_products(){

        $products = $this->product
        ->select_raw(['products.*', 'count(*) as num_monitoring', 'TIMESTAMPDIFF(HOUR, `last_checked`, NOW()) as time_difference'])
        ->join('grocery_list_items', 'grocery_list_items.product_id', 'products.id')
        ->join('monitored_products', 'monitored_products.product_id', 'products.id')
        ->join('favourite_products', 'favourite_products.product_id', 'products.id')
        ->where_raw(['TIMESTAMPDIFF(HOUR, `last_checked`, NOW()) = 0'])
        // ->where_raw(['products.id = 5'])
        ->group_by('products.id')
        ->order_by('num_monitoring')
        // ->limit(1)
        ->get();

        // fix image saving everytime to once a day
        foreach($products as $product_item){

            if(is_null($product_item) || $product_item->num_monitoring == 0){
                return;
            }

            $this->logger->notice("------- Asda Product Start -----------");
            $this->logger->debug("Last Checked: {$product_item->time_difference} Hours Ago");
            $this->logger->debug("Product: [{$product_item->id}] {$product_item->name}");
            $this->check_product_change($product_item);
            $this->logger->notice("------- Asda Product Complete --------");
        }

    }

    public function check_product_change($product_item){
        
        $asda_product = new AsdaProducts($this->config, $this->logger, $this->database, null);

        $new_product = $asda_product->product_details($product_item->site_product_id);

        $notify_user = false;
        $product_changed = false;

        $monitor_fields = ['name', 'description', 'price','site_product_id'];
        $update_fields  = ['last_checked' => date('Y-m-d H:i:s')];
        
        foreach($monitor_fields as $field){
            if($product_item->{$field} != $new_product->{$field}){
                $this->logger->notice("Product $field changed: {$product_item->{$field}} -> {$new_product->{$field}}");
                $product_changed = true;
                $notify_user = $field == 'price' ? true : false;
                $update_fields[$field] = $new_product->{$field};
            }
        }

        /////////////////////  Start Transaction ////////////////////////
        $this->database->start_transaction();

        // Check to see for new reviews
        $this->review->create_review($product_item->id, $new_product->site_product_id);
        // Check to see if recommened changed
        $this->recommended->product_recommended($product_item->id, $new_product->site_product_id);
        
        // Check to see if promotion still exists

        $this->product->where(['id' => $product_item->id])->update($update_fields);

        if($product_changed){
            $this->logger->info('Updating Product Information For: ' . $product_item->id);
        } else {
            $this->logger->info('Product Not Changed For: ' . $product_item->id);
        }

        if($notify_user){
            $this->notify_product_changes($product_item);
        }

        $this->database->commit_transaction();
        /////////////////////  End Transaction  ////////////////////////

    }

    public function notify_product_changes($product_item){

        $favourite = new FavouriteModel($this->database);
        $grocery   = new GroceryListItemModel($this->database);
        $monitor   = new MonitoredProductModel($this->database);
        $user_info = new UserModel($this->database);

        $favourites_users = $favourite->where(['product_id' => $product_item->id])->group_by('user_id')->get();
        $grocery_users    = $grocery->join('grocery_lists', 'grocery_lists.id', 'grocery_list_items.list_id')->where(['product_id' => $product_item->id])->group_by('user_id')->get();
        $monitored_users  = $monitor->where(['product_id' => $product_item->id])->group_by('user_id')->get();

        $unique_users = [];

        foreach(array_merge($favourites_users, $grocery_users, $monitored_users) as $user_item){

            $user_id = $user_item->user_id;
            
            if(!key_exists($user_id, $unique_users)){
                $unique_users[$user_id] = 1;

                $user = $user_info->where(['id' => $user_id])->get()[0] ?? null;

                if($user){
                    $this->notification->notify_change($product_item, $user);
                } else {
                    throw new Exception('User not found in database: ' . $user_id);
                }

            }

        }
    }

}

?>