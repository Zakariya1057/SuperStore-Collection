<?php

namespace Stores\Asda;

use Exception;
use Models\Product\ProductModel;
use Models\Shared\FavouriteModel;
use Models\Shared\GroceryListItemModel;
use Models\Shared\MonitoredProductModel;
use Models\Shared\UserModel;

class AsdaMonitor extends Asda {

    public $notification;

    function __construct($config,$logger,$database,$remember, $notification){
        parent::__construct($config,$logger,$database,$remember);
        $this->notification = $notification;
    }

    public function monitor_products(){

        $asda_product = new AsdaProducts($this->config, $this->logger, $this->database, null);

        $product   = new ProductModel($this->database);
        $favourite = new FavouriteModel($this->database);
        $grocery   = new GroceryListItemModel($this->database);
        $monitor   = new MonitoredProductModel($this->database);
        $user_info = new UserModel($this->database);

        $products = $product
        ->select_raw(['products.*', 'count(*) as num_monitoring'])
        ->join('grocery_list_items', 'grocery_list_items.product_id', 'products.id')
        ->join('monitored_products', 'monitored_products.product_id', 'products.id')
        ->join('favourite_products', 'favourite_products.product_id', 'products.id')
        ->where_raw(['TIMESTAMPDIFF(HOUR, `last_checked`, NOW()) > 3', 'changed = 0'])
        // ->where_raw(['products.id = 5'])
        ->group_by('products.id')
        ->order_by('products.id','asc')
        // ->limit(1)
        ->get();

        // fix image saving everytime to once a day
        foreach($products as $product_item){

            if(is_null($product_item) || $product_item->num_monitoring == 0){
                return;
            }

            $new_product = $asda_product->product_details($product_item->site_product_id);
            $product_changed = false;

            // Check if price, description or promotion has changed.
            $monitor_fields = ['name', 'description', 'price'];
            $update_fields  = ['changed' => 1, 'last_checked' => date('Y-m-d H:i:s')];
            
            foreach($monitor_fields as $field){
                $update_fields[$field] = $new_product->{$field};

                if($product_item->{$field} != $new_product->{$field}){
                    $this->logger->notice("Product $field changed: {$product_item->{$field}} != {$new_product->{$field}}");
                    $product_changed = true;
                    break;
                }
            }

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

            if($product_changed){
                $product->where(['id' => $product_item->id])->update($update_fields);
                $this->logger->info('Updating Product Information For: ' . $product_item->id);
            }

        }

    }

}

?>