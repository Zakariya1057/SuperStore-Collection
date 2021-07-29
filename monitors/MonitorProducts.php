<?php

namespace Monitors;

use Collection\Services\SharedProductPriceService;
use Exception;

use Collection\Services\SharedProductService;

use Interfaces\ProductInterface;

use Models\Product\IngredientModel;
use Models\Product\ProductModel;
use Models\Product\ProductPriceModel;
use Models\Shared\MonitoredProductModel;

use Services\ConfigService;
use Services\CurrencyService;
use Services\DatabaseService;
use Services\NotificationService;
use Services\SanitizeService;

use Monolog\Logger;
class MonitorProducts {
    
    private $notification_service, $logger, $database_service;
    
    private $product_price_model, $product_model, $monitor_model, $ingredients_model;

    private $currency_service, $sanitize_service;
    
    private $product_collection;

    private $product_service, $product_price_service;

    function __construct(ConfigService $config_service, Logger $logger, DatabaseService $database_service, ProductInterface $product_collection){
        $this->logger = $logger;
        $this->database_service = $database_service;

        $this->product_model = new ProductModel($database_service);
        $this->product_price_model = new ProductPriceModel($database_service);
        $this->monitor_model = new MonitoredProductModel($database_service);
        $this->ingredients_model = new IngredientModel($database_service);

        $this->product_collection = $product_collection;

        $this->notification_service = new NotificationService($config_service, $logger);

        $this->sanitize_service = new SanitizeService();
        $this->currency_service = new CurrencyService();

        $this->product_service = new SharedProductService($database_service);
        $this->product_price_service = new SharedProductPriceService($database_service);
    }

    private function get_products(){

        $product_results = $this->product_model
        ->select_raw([
            'products.*', 
            // 'count(*) as num_monitoring', 
            'TIMESTAMPDIFF(HOUR, `last_checked`, NOW()) as time_difference',
            
            'product_prices.region_id as product_region_id', 'price as product_price', 
            'old_price as product_old_price', 'is_on_sale as product_is_on_sale', 
            'sale_ends_at product_sale_ends_at', 'promotion_id as product_promotion_id'
        ])
        ->join('product_prices', 'product_prices.product_id', 'products.id')
        // ->where_raw(["products.site_product_id = 21359663_EA"])
        // ->where_raw(["company_id = $company_id", 'products.large_image is null'])
        ->where_raw(['TIMESTAMPDIFF(HOUR, `last_checked`, NOW()) > 3'])
        // ->where_raw(["products.site_product_id = '21359663_EA'"])
        // ->group_by('products.id')
        // ->where(['products.id' => 1])
        // ->order_by('num_monitoring')
        // ->limit(1000)
        ->order_by('last_checked')
        ->get();

        $products = [];

        $product_price_fields = ['region_id', 'price', 'old_price', 'is_on_sale', 'sale_ends_at', 'promotion_id'];

        foreach($product_results as $product){
            $product_id = $product->id;

            $product_price = new ProductPriceModel($this->database_service);
            $product_price->product_id = $product_id;

            foreach($product_price_fields as $field){
                $query_field = 'product_' . $field;
                $product_price->{$field} = $product->{$query_field};
            }

            if(key_exists($product->id, $products)){
                $cached_product = $products[$product->id];
                $cached_product->prices[] = $product_price;
            } else {
                $product->prices = [$product_price];
                $products[$product_id] = $product;
            }
        }

        return array_values($products);
    }

    // Shared Monitor, check if data has changed, if so update in database.
    public function monitor_products($store_type){
        $products = $this->get_products();

        $this->logger->notice('Total Products Count: '. count($products));

        foreach($products as $product){
            if(is_null($product)){
                return;
            } else {
                $this->check_product($product);
            }
        }
    }

    private function check_product($old_product){
        $id = $old_product->id;
        $name = $old_product->name;
        $last_checked = $old_product->time_difference;

        $site_product_id = $old_product->site_product_id;

        $this->logger->notice("------- Checking $name Product Start -----------");
        $this->logger->debug("Last Checked: $last_checked Hours Ago");
        $this->logger->debug("Updating Product: [$id] $name");

        // Only fetch images if no product image found
        // $ignore_image = !is_null($old_product->large_image);
        $ignore_image = false;

        $new_product = $this->product_collection->product_details($site_product_id, $ignore_image);

        if(is_null($new_product)){
            $this->logger->error('Failed To Find Product: ' . $site_product_id);
        } else {
            $this->check_product_change($new_product, $old_product);
        }
        
        $this->logger->notice("------- Checking $name Product Complete --------");
    }

    private function check_product_change(ProductModel $new_product, $old_product){
        $this->database_service->start_transaction();

        $product_id = $old_product->id;

        // If product price changes, notify relevant users.
        $update_fields = ['last_checked' => date('Y-m-d H:i:s')];

        $price_changes = $this->price_check($new_product, $old_product);

        $this->details_check($new_product, $old_product, $update_fields);
        $this->image_check($new_product, $old_product, $update_fields);

        // $this->available_check($new_product, $old_product, $update_fields);
        
        $this->product_model->where(['id' => $product_id])->update($update_fields);

        foreach($price_changes as $price_change){
            $region_id = $price_change->region_id;
            $supermarket_chain_id = $price_change->supermarket_chain_id;

            $this->product_price_model->where([
                'product_id' => $product_id, 
                'region_id' => $region_id, 
                'supermarket_chain_id' => $supermarket_chain_id
            ])
            ->update($price_change);

            if(property_exists($price_change, 'price')){
                $this->notify_product_changed($old_product, $price_change);
            }
        }

        $this->database_service->commit_transaction();
        
    }

    private function notify_product_changed($product, $price_change){

        $this->logger->debug('Product Price Changed. Sending Notification');

        $product_id = $product->id;

        $monitored_users = $this->monitor_model
        ->where(['users.region_id' => $price_change->region_id, 'product_id' => $product_id, 'send_notifications' => 1])
        ->join('users', 'monitored_products.user_id', 'users.id')
        ->group_by('user_id')->get();

        $data = ['product_id' => (int)$product_id];

        foreach($monitored_users as $user){
            $notification_message = $this->create_notification_message($product, $price_change->price, $price_change->old_price);
            
            try {
                $this->notification_service->send_notification($user, $data, $notification_message);
            } catch(Exception $e){
                $this->logger->error('Notification Error: '. $e->getMessage());
            }
        }
    }

    private function create_notification_message($product, $new_price, $old_price){
        $product_name = $product->name;

        $currency = $this->currency_service->get_currency_symbol($product->currency);

        $new_price = number_format($new_price, 2);
        $old_price = number_format($old_price, 2);

        $changed_type = $new_price > $old_price ? 'Increased' : 'Decreased';

        $title = 'Product Price Change';
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
            'url',
            'large_image',
            'small_image',
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

    private function price_check(ProductModel $new_product, $old_product){
        $price_changes = [];

        $supermarket_prices = $this->product_price_service->group_prices($new_product->prices, $old_product->prices);

        foreach($supermarket_prices as $supermarket_chain_id => $prices){

            // Number of prices have changed, either an increase or decreate.
            if(count($new_product->prices) != count($old_product->prices)){

                $this->logger->notice('Number Of Product Prices Changed: ' . count($new_product->prices) . ' != ' . count($old_product->prices));

                foreach($prices as $region_id => $product_prices){
                    $new_price = $product_prices->new_price;
                    $new_price->product_id = $old_product->id;

                    $this->product_price_service->create($new_price);
                }

                $this->logger->notice('Created All The Product Prices');
            }

            foreach($prices as $region_id => $product_prices){
                $new_price = $product_prices->new_price ?? null;
                $old_price = $product_prices->old_price ?? null;
    
                if(is_null($old_price)){
                    // Only new product prices are found.
                    $this->product_price_service->create($new_price);
                    continue;
                } else if(is_null($new_price)){
                    // Previosly found, not found now
                    throw new Exception('Previosly found, not found now');
                }
    
                $this->logger->debug("Checking Price Changes For [$supermarket_chain_id] Region: $region_id");
                
                $region_price_changes = [
                    'region_id' => $new_price->region_id,
                    'supermarket_chain_id' => $supermarket_chain_id
                ];
    
                $old_value = $old_price->price;
                $new_value = $new_price->price;
    
                $this->sale_check($new_price, $region_price_changes);
                $this->promotion_check($new_price, $old_price, $region_price_changes);
    
                if($old_value != $new_value && $new_value > 0){
                    $this->logger->debug("Price Changed: $old_value -> $new_value");
                    $region_price_changes['price'] = $new_value;
                    $region_price_changes['old_price'] = $old_value;
                }
    
                $price_changes[] = (object)$region_price_changes;
    
            }

        }

        return $price_changes;
    }

    private function sale_check(ProductPriceModel $new_prices, &$update_fields){
        if(!is_null($new_prices->is_on_sale)){
            // New Sale Added
            $update_fields['is_on_sale'] = 1;
            $update_fields['old_price'] = $new_prices->old_price;
            $update_fields['sale_ends_at'] = $new_prices->sale_ends_at;
        } else {
            // Sale Expired or No Sale
            $update_fields['is_on_sale'] = null;
            $update_fields['old_price'] = null;
            $update_fields['sale_ends_at'] = null;
        }
    }

    private function promotion_check(ProductPriceModel $new_prices, ProductPriceModel $old_prices, &$update_fields){
        if( !property_exists($new_prices, 'promotion') || is_null($new_prices->promotion) ){
            $update_fields['promotion_id'] = null;

            if(is_null($old_prices->promotion_id)){
                // $this->logger->debug('No Product Promotion Before Or After');
            } else {
                $this->logger->debug('Promotion Expired Or Product Removed From Promotions');
            }
        } else {
            $this->logger->debug('Promotion Found For Product');

            $new_promotion_id = $this->product_service->create_promotion($new_prices->promotion);

            if(is_null($old_prices->promotion_id) || $old_prices->promotion_id != $new_promotion_id){
                $this->logger->debug('Updating Changed Product Promotion');
                $update_fields['promotion_id'] = $new_promotion_id;
            } else {
                $this->logger->debug('Product Promotion Has Stayed The Same');
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