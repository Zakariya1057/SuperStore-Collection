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
use Services\Config;
use Services\Database;

// Index Documents
class Indices extends Search {

    private $product_model, $promotion_model, $store_model, $category_product_model;
    private $product_group_model,$child_category_model, $parent_category_model, $grand_parent_category_model;

    private $parent_categories, $child_categories;

    function __construct(Config $config, Logger $logger, Database $database, Client $client){
        parent::__construct($config, $logger, $database, $client);

        $this->product_model = new ProductModel($database);
        $this->store_model = new StoreTypeModel($database);
        $this->promotion_model = new PromotionModel($database);

        $this->category_product_model = new CategoryProductModel($database);

        $this->product_group_model = new ProductGroupModel($database);
        $this->child_category_model = new ChildCategoryModel($database);
        $this->parent_category_model = new ParentCategoryModel($database);
        $this->grand_parent_category_model = new GrandParentCategoryModel($database);
    }

    public function index_products(){

        $this->logger->debug('Indexing Products');

        $this->delete_documents('products');

        $this->set_categories();
        
        $results = $this->product_model->select_raw('COUNT(*) as total_count')->where(['enabled' => 1])->get();
        $product_count = $results[0]->total_count;

        // Loop through groups of 5000 products.
        for($i = 0; $i < $product_count; $i += 1000){
            $products = $this->product_model->limit("$i,1000")->get();
            $this->logger->debug("Indexing Product Groups: $i,1000");
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
        
            [$parent_category_names, $child_category_names] = $this->get_all_category_names($product->id);

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

                'parent_category_names' => $parent_category_names,
                'child_category_names' => $child_category_names,
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


    private function set_categories(){
        // For Store Type Set Categories.
        // [Category ID] -> [Name]
        $parent_categories_results = $this->parent_category_model->select(['id', 'name'])->get();
        $child_categories_results = $this->child_category_model->select(['id', 'name'])->get();

        foreach($parent_categories_results as $parent_category){
            $id = $parent_category->id;
            $name = $parent_category->name;
            $this->parent_categories[$id] = $name;
        }

        foreach($child_categories_results as $child_category){
            $id = $child_category->id;
            $name = $child_category->name;
            $this->child_categories[$id] = $name;
        }
    }

    private function get_all_category_names($product_id){
        // Combined All Product Categories
        $category_product_results = $this->category_product_model
        ->where(['product_id' => $product_id])
        ->get();

        $parent_category_names_list = [];
        $child_category_names_list = [];

        foreach($category_product_results as $category_product){
            $parent_category_names_list[] = $this->parent_categories[$category_product->parent_category_id];
            $child_category_names_list[] = $this->child_categories[$category_product->child_category_id];
        }

        $parent_categories = join(' ', $parent_category_names_list);
        $child_categories = join(' ', $child_category_names_list);

        return [$parent_categories, $child_categories];
    }
}

?>