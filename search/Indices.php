<?php

namespace Search;

use Elasticsearch\Client;
use Models\Category\CategoryProductModel;
use Models\Category\ChildCategoryModel;
use Models\Category\GrandParentCategoryModel;
use Models\Category\ParentCategoryModel;
use Models\Category\ProductGroupModel;
use Models\Product\ProductModel;
use Models\Product\PromotionModel;
use Models\Store\StoreTypeModel;
use Monolog\Logger;
use Services\ConfigService;
use Services\DatabaseService;

// Index Documents
class Indices extends Search {

    private $product_model, $promotion_model, $store_model;
    private $product_group_model, $child_category_model, $parent_category_model, $grand_parent_category_model;

    private $parent_categories, $child_categories, $product_groups;

    function __construct(ConfigService $config_service, Logger $logger, DatabaseService $database_service, Client $client){
        parent::__construct($config_service, $logger, $database_service, $client);

        $this->product_model = new ProductModel($database_service);
        $this->store_model = new StoreTypeModel($database_service);
        $this->promotion_model = new PromotionModel($database_service);

        $this->category_product_model = new CategoryProductModel($database_service);

        $this->product_group_model = new ProductGroupModel($database_service);
        $this->child_category_model = new ChildCategoryModel($database_service);
        $this->parent_category_model = new ParentCategoryModel($database_service);
        $this->grand_parent_category_model = new GrandParentCategoryModel($database_service);
    }

    public function index_products(){

        $this->logger->debug('Indexing Products');

        $this->delete_documents('products');

        $this->set_categories();
        
        $results = $this->product_model->select_raw('COUNT(*) as total_count')->where(['enabled' => 1])->get();
        $product_count = $results[0]->total_count;

        // Loop through groups of 5000 products.
        for($index = 0; $index < $product_count; $index += 1000){
            $products = $this->get_products($index);

            if(count($products) == 0){
                break;
            }

            $this->logger->debug("Indexing Product Groups: $index,1000");
            $this->index_product_group($products);
        }
    }

    public function index_stores(){

        $this->logger->debug('Indexing Store Types');

        $this->delete_documents('stores');

        $stores = $this->store_model->where(['enabled' => 1])->get();

        $params = [
            'body' => []
        ];

        foreach($stores as $store){
            $params['body'][] = [
                'index' => [
                    '_index' => 'stores',
                    '_id'    => $store->id
                ]
            ];

            $params['body'][] = [
                'id' => (int)$store->id,
                'store_type_id' => (int)$store->id,
                'name' => $store->name,
            ];
        }

        $responses = $this->client->bulk($params);
    }

    public function index_promotions(){

        $this->logger->debug('Indexing Promotions');

        $this->delete_documents('promotions');

        $promotions = $this->promotion_model->where(['enabled' => 1])->get();

        $params = [
            'body' => []
        ];

        foreach($promotions as $promotion){
            $params['body'][] = [
                'index' => [
                    '_index' => 'promotions',
                    '_id'    => $promotion->id
                ]
            ];

            $params['body'][] = [
                'id' => (int)$promotion->id,
                'store_type_id' => (int)$promotion->store_type_id,
                'name' => $promotion->name,
            ];
        }

        $responses = $this->client->bulk($params);
    }

    public function index_categories(){

        $this->logger->debug('Indexing Categories');

        $this->delete_documents('categories');

        $categories = array_merge(
            $this->child_category_model->select_raw(['*', '"child_categories" as "type"'])->where(['enabled' => 1])->get(), 
            $this->product_group_model->select_raw(['*', '"product_groups" as "type"'])->where(['enabled' => 1])->get(), 
            $this->parent_category_model->select_raw(['*', '"parent_categories" as "type"'])->where(['enabled' => 1])->get(),
            $this->grand_parent_category_model->select_raw(['*', '"grand_parent_categories" as "type"'])->where(['enabled' => 1])->get()
        );

        $params = [
            'body' => []
        ];

        foreach($categories as $category){
            $params['body'][] = [
                'index' => [
                    '_index' => 'categories',
                    '_id'    => $category->type .'_'. $category->id
                ]
              ];
      
            $params['body'][] = [
              'id'   => (int)$category->id,
              'store_type_id' => (int)$category->store_type_id,
              'name' => $category->name,
              'type' => $category->type
            ];
        }

        $responses = $this->client->bulk($params);

    }

    private function index_product_group($products){

        $params = ['body' => []];

        foreach($products as $product){
            $params['body'][] = [
                'index' => [
                    '_index' => 'products',
                    '_id'    => $product->id
                ]
            ];

            $params['body'][] = [
                'id' => (int)$product->id,
                'name' => $product->name,
                'store_type_id' => (int)$product->store_type_id,
                'description' => $product->description,
                'price' => (float)$product->price,
                'weight' => $product->weight,
                'brand' => $product->brand,
                'dietary_info' => $product->dietary_info,
                'allergen_info' => $product->allergen_info,
                'avg_rating' => (float)$product->avg_rating,
                'total_reviews_count' => (float)$product->total_reviews_count,

                'parent_category_names' => $product->parent_categories,
                'child_category_names' => $product->child_categories,
                'product_group_names' => $product->product_groups
            ];
            
        }

        return $this->client->bulk($params);

    }

    public function delete_documents($index){
        $this->client->deleteByQuery([
            'index' => $index,
            'body' => [
                'query' => [
                    'match_all' => (object)[]
                ]
            ]
        ]);
    }


    private function get_products($index){
        $product_results = $this->product_model
        ->select(['products.*', 'child_category_id', 'parent_category_id', 'product_group_id'])
        ->join('category_products', 'category_products.product_id', 'products.id')
        ->where_not_in('product_group_id', [0])
        ->limit("$index,1000")
        ->get();

        $products = [];

        foreach($product_results as $product){
            $product_id = $product->id;

            $child_category_name = $this->child_categories[$product->child_category_id];
            $product_group_name = $this->product_groups[$product->product_group_id] ?? null;
            $parent_category_name = $this->parent_categories[$product->parent_category_id];

            if(key_exists($product->id, $products)){
                $product_item = $products[$product_id];

                $child_category_names_list = $product_item->child_category_names_list;
                $product_group_names_list =  $product_item->product_group_names_list;
                $parent_category_names_list = $product_item->parent_category_names_list;

                $child_category_names_list[] = $child_category_name;

                if(!is_null($product_group_name)){
                    $product_group_names_list[] = $product_group_name;
                }

                $parent_category_names_list[] = $parent_category_name;

                $products[$product_id]->child_category_names_list = $child_category_names_list;
                $products[$product_id]->product_group_names_list = $product_group_names_list;
                $products[$product_id]->parent_category_names_list = $parent_category_names_list;
            } else {
                $product->child_category_names_list = [ $child_category_name ];
                $product->product_group_names_list = [ $product_group_name ];
                $product->parent_category_names_list = [ $parent_category_name ];

                $products[$product_id] = $product;
            }
        }

        $category_fields = [
            'child_categories' => 'child_category_names_list',
            'product_groups' => 'product_group_names_list',
            'parent_categories' => 'parent_category_names_list'
        ];

        foreach($products as $product){
            foreach($category_fields as $category_type => $category_field){
                $product->{$category_type} = join(' ', array_unique($product->{$category_field}));
                unset($product->{$category_field});
            }
        }

        return $products;
    }

    private function set_categories(){
        // For Store Type Set Categories.
        // [Category ID] -> [Name]
        $parent_category_results = $this->parent_category_model->select(['id', 'name'])->where(['enabled' => 1])->get();
        $child_category_results = $this->child_category_model->select(['id', 'name'])->where(['enabled' => 1])->get();
        $product_group_results = $this->product_group_model->select(['id', 'name'])->where(['enabled' => 1])->get();

        foreach($parent_category_results as $parent_category){
            $id = $parent_category->id;
            $name = $parent_category->name;
            $this->parent_categories[$id] = $name;
        }

        foreach($child_category_results as $child_category){
            $id = $child_category->id;
            $name = $child_category->name;
            $this->child_categories[$id] = $name;
        }

        foreach($product_group_results as $product_group){
            $id = $product_group->id;
            $name = $product_group->name;
            $this->product_groups[$id] = $name;
        }
    }

}

?>