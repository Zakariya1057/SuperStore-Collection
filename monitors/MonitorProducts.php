<?php

namespace Monitors;

use Collection\Services\SharedProductService;
use Exception;
use Interfaces\ProductInterface;
use Interfaces\PromotionInterface;
use Models\Product\IngredientModel;
use Models\Product\ProductModel;
use Models\Shared\MonitoredProductModel;
use Services\Notification;
use Monolog\Logger;
use Services\Database;
use Services\Config;
use Services\Currency;
use Services\Sanitize;

class MonitorProducts {
    
    private $notification, $logger, $database;
    
    private $product_model, $monitor_model, $ingredients_model;

    private $currency_service, $sanitize_service;
    
    private $product_collection;

    private $product_service;

    function __construct(Config $config, Logger $logger, Database $database, ProductInterface $product_collection, PromotionInterface $promotion_collection = null){
        $this->logger = $logger;
        $this->database = $database;

        $this->product_model = new ProductModel($database);
        $this->monitor_model = new MonitoredProductModel($database);
        $this->ingredients_model = new IngredientModel($database);

        $this->product_collection = $product_collection;

        $this->notification = new Notification($config, $logger);

        $this->sanitize_service = new Sanitize();
        $this->currency_service = new Currency();

        $this->product_service = new SharedProductService($database);
    }

    // Shared Monitor, check if data has changed, if so update in database.
    public function monitor_products($store_type){
        $store_type_id = $store_type->store_type_id;

        $products = $this->product_model
        ->select_raw(['products.*', 'count(*) as num_monitoring', 'TIMESTAMPDIFF(HOUR, `last_checked`, NOW()) as time_difference'])
        ->join('grocery_list_items', 'grocery_list_items.product_id', 'products.id')
        ->join('monitored_products', 'monitored_products.product_id', 'products.id')
        ->join('favourite_products', 'favourite_products.product_id', 'products.id')
        // ->where_raw(["products.id = 18854"])
        // ->where_raw(["store_type_id = $store_type_id", 'products.large_image is null'])
        ->where_raw(["store_type_id = $store_type_id", 'TIMESTAMPDIFF(HOUR, `last_checked`, NOW()) > 3'])
        ->group_by('products.id')
        ->order_by('num_monitoring')
        // ->limit(100)
        ->get();

        $this->logger->notice('Total Products Count: '. count($products));

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

            // Only fetch images if no product image found
            $ignore_image = !is_null($product->large_image);

            $new_product = $this->product_collection->product_details($site_product_id, $ignore_image);

            if(is_null($new_product)){
                $this->logger->error('Failed To Find Product: ' . $site_product_id);
            } else {
                $this->check_product_change($new_product, $product);
            }

            
            $this->logger->notice("------- Checking $name Product Complete --------");
        }

    }

    public function check_product_change(ProductModel $new_product, $old_product){
        $this->database->start_transaction();

        $product_id = $old_product->id;

        // If product price changes, notify relevant users.
        $update_fields = ['last_checked' => date('Y-m-d H:i:s')];

        $price_changed = $this->price_check($new_product, $old_product, $update_fields);
        $this->promotion_check($new_product, $old_product, $update_fields);

        $this->details_check($new_product, $old_product, $update_fields);
        $this->image_check($new_product, $old_product, $update_fields);

        $this->available_check($new_product, $old_product, $update_fields);
        
        $this->sale_check($new_product, $old_product, $update_fields);
        
        $this->product_model->where(['id' => $product_id])->update($update_fields);

        $this->database->commit_transaction();

        if($price_changed){
            $this->notify_product_changed($new_product, $old_product);
        }
        
    }

    private function notify_product_changed($product, $old_product){

        $this->logger->debug('Product Price Changed. Sending Notification');

        $monitored_users  = $this->monitor_model
        ->where(['product_id' => $old_product->id, 'send_notifications' => 1])
        ->join('users', 'monitored_products.user_id', 'users.id')
        ->group_by('user_id')->get();

        $data = ['product_id' => (int)$old_product->id];

        foreach($monitored_users as $user){
            $notification_message = $this->create_notification_message($product, $old_product);
            try {
                $this->notification->send_notification($user, $data, $notification_message);
            } catch(Exception $e){
                $this->logger->error('Notification Error: '. $e->getMessage());
            }
        }
    }

    private function create_notification_message($product, $old_product){
        $product_name = $product->name;

        $currency = $this->currency_service->get_currency_symbol($product->currency);

        $new_price = number_format($product->price, 2);
        $old_price = number_format($old_product->price, 2);

        $changed_type = $new_price > $old_price ? 'Increased' : 'Decreased';

        $title = "Product Price Change";
        $content = "$product_name - $changed_type from {$currency}{$old_price} to {$currency}{$new_price}";

        return ['title' => $title, 'body' => $content];
    }

    private function available_check($new_product, $old_product, &$update_fields){
        if((int)$old_product->available != (int)$new_product->available){
            $update_fields['available'] = $new_product->available;
        }   
    }

    private function details_check(ProductModel $new_product, $old_product, &$update_fields){
        $check_fields = [
            'name',
            'description',
            'features',
            'dimensions',
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

    private function image_check(ProductModel $new_product, $old_product, &$update_fields){
        if(is_null($old_product->large_image) && !is_null($new_product->large_image)){
            $this->logger->debug('Saving New Product Images');
            $update_fields['small_image'] = $new_product->small_image;
            $update_fields['large_image'] = $new_product->large_image;
        }
    }

    private function price_check(ProductModel $new_product, $old_product, &$update_fields){
        $price_changed = false;

        $old_value = $old_product->price;
        $new_value = $new_product->price;

        if($old_value != $new_value){
            $this->logger->debug("Price Changed: $old_value -> $new_value");
            $price_changed = true;
            $update_fields['price'] = $new_value;
            $update_fields['old_price'] = null;
        }

        return $price_changed;
    }

    private function sale_check(ProductModel $new_product, $old_product, &$update_fields){
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

    private function promotion_check(ProductModel $new_product, $old_product, &$update_fields){
        if(!property_exists($new_product, 'promotion') || is_null($new_product->promotion)){
            $update_fields['promotion_id'] = null;

            if(is_null($old_product->promotion_id)){
                $this->logger->debug('No Product Promotion Before Or After');
            } else {
                $this->logger->debug('Promotion Expired Or Product Removed From Promotions');
            }
        } else {
            $this->logger->debug('Promotion Found For Product');
            $this->product_service->create_promotion($new_product);

            if(is_null($old_product->promotion_id) || $old_product->promotion_id != $new_product->promotion_id){
                $this->logger->debug('Updating Changed Product Promotion');
                $update_fields['promotion_id'] = $new_product->promotion_id;
            }
        }
    }

    private function ingredients_check($product_id, $new_product){
        $new_ingredients = array_values($new_product->ingredients);
        $old_ingredients = $this->ingredients_model->where(['product_id' => $product_id])->get() ?? [];

        $new_ingredients_count = count($new_ingredients);
        $old_ingredients_count = count($old_ingredients);

        if($new_ingredients_count == $old_ingredients_count){
            // Update Ingredient Name. Expand Truncated Ingredient Name
            foreach($new_ingredients as $index => $ingredient){
                if(!key_exists($index, $old_ingredients)){
                    throw new Exception('Ingredient Not Found At Index: '. $index);
                }

                $old_name = $old_ingredients[$index]->name;
                $ingredient = $this->sanitize_service->sanitize_field($ingredient);

                $this->ingredients_model
                ->where([
                    'product_id' => $product_id
                ])
                ->like(['name'=> "$old_name%"])
                ->limit("1")
                ->update([
                    'name' => $ingredient
                ]);
            }

        } else {
            throw new Exception("Number Of Ingredients Changed: $old_ingredients_count vs $new_ingredients_count");
        }
    }
}

?>