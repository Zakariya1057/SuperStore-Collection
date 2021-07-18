<?php

require_once './vendor/autoload.php';

use Models\Category\CategoryProductModel;
use Models\Category\ChildCategoryModel;
use Models\Category\GrandParentCategoryModel;
use Models\Category\ParentCategoryModel;
use Models\Category\ProductGroupModel;
use Models\Featured\FeaturedModel;
use Services\DatabaseService;
use Services\ConfigService;
use Services\LoggerService;
use Services\RequestService;

use Models\Grocery\GroceryListModel;
use Models\Grocery\GroceryListItemModel;

use Models\Shared\MonitoredProductModel;
use Models\Product\BarcodeModel;
use Models\Product\IngredientModel;
use Models\Product\ProductModel;
use Models\Product\PromotionModel;
use Models\Product\RecommendedModel;
use Models\Product\ReviewModel;
use Models\Shared\FavouriteModel;
use Models\Store\FacilityModel;
use Models\Store\LocationModel;
use Models\Store\OpeningHourModel;
use Models\Store\StoreModel;

$config_service = new ConfigService();

$log = new LoggerService('Migration');
$logger = $log->logger_handler;

$request = new RequestService($config_service, $logger);

$database_service = new DatabaseService($config_service, $logger);

$logger->debug('------ Asda Deleting Start ------');
$database_service->start_transaction();

delete_stores($database_service);
delete_grocery_lists($database_service);
delete_featued_items($database_service);
delete_products($database_service);
delete_promotions($database_service);
delete_categories($database_service);

function delete_stores(DatabaseService $database_service){
    $store_model = new StoreModel($database_service);
    $store_location_model = new LocationModel($database_service);
    $facility_model = new FacilityModel($database_service);
    $hour_model = new OpeningHourModel($database_service);

    $stores = $store_model->where(['supermarket_chain_id' => 1])->get();

    foreach($stores as $store){
        $store_location_model->where(['store_id' => $store->id])->delete();
        $facility_model->where(['store_id' => $store->id])->delete();
        $hour_model->where(['store_id' => $store->id])->delete();
        $store_model->where(['id' => $store->id])->delete();
    }
}

function delete_categories(DatabaseService $database_service){
    $child_categories_model = new ChildCategoryModel($database_service);
    $parent_categories_model = new ParentCategoryModel($database_service);
    $grand_parent_categories_model = new GrandParentCategoryModel($database_service);

    $product_groups_model = new ProductGroupModel($database_service);

    $grocery_list_item_model = new GroceryListItemModel($database_service);

    $product_groups_model->where(['company_id' => 1])->delete();
    $child_categories_model->where(['company_id' => 1])->delete();

    $categories = $parent_categories_model->where(['company_id' => 1])->get();
    foreach($categories as $category){
        $grocery_list_item_model->where(['parent_category_id' => $category->id])->delete();
        $parent_categories_model->where(['id' => $category->id])->delete();
    }

    $grand_parent_categories_model->where(['company_id' => 1])->delete();
}

function delete_featued_items(DatabaseService $database_service){
    $featured_model = new FeaturedModel($database_service);
    $featured_model->where(['company_id' => 1])->delete();
}

function delete_grocery_lists(DatabaseService $database_service){
    $grocery_list_model = new GroceryListModel($database_service);
    $grocery_list_item_model = new GroceryListItemModel($database_service);

    $grocery_lists = $grocery_list_model->select(['id'])->where(['company_id' => 1])->get();

    foreach($grocery_lists as $grocery_list){
        $grocery_list_item_model->where(['list_id' => $grocery_list->id])->delete();
        $grocery_list_model->where(['id' => $grocery_list->id])->delete();
    }
}

function delete_products(DatabaseService $database_service){
    $product_model = new ProductModel($database_service);
    $ingredient_model = new IngredientModel($database_service);
    $review_model = new ReviewModel($database_service);
    $barcode_model = new BarcodeModel($database_service);
    $favourite_model = new FavouriteModel($database_service);

    $grocery_list_item_model = new GroceryListItemModel($database_service);

    $category_product = new CategoryProductModel($database_service);

    $monitoring_model = new MonitoredProductModel($database_service);
    $recommended_model = new RecommendedModel($database_service);

    while(true){

        $product_ids = [];
        $products = $product_model->where(['company_id' => 1])->limit(500)->get();

        foreach($products as $product){
            $product_ids[] = $product->id;
        }

        if(count($product_ids) > 0){
            $ingredient_model->where_in('product_id', $product_ids)->delete();
            $review_model->where_in('product_id', $product_ids)->delete();
            $barcode_model->where_in('product_id', $product_ids)->delete();
            $category_product->where_in('product_id', $product_ids)->delete();
            $favourite_model->where_in('product_id', $product_ids)->delete();
            $monitoring_model->where_in('product_id', $product_ids)->delete();
            $recommended_model->where_in('product_id', $product_ids)->delete();
            $recommended_model->where_in('recommended_product_id', $product_ids)->delete();
            $grocery_list_item_model->where_in('product_id', $product_ids)->delete();
    
            $product_model->where_in('id', $product_ids)->delete();
        } else {
            break;
        }

    }

}

function delete_promotions(DatabaseService $database_service){
    $promotion_model = new PromotionModel($database_service);
    $promotion_model->where(['supermarket_chain_id' => 1])->delete();
}

$database_service->commit_transaction();
$logger->debug('------ Asda Deleting Complete ------');

?>