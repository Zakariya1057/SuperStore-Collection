<?php

namespace ElasticSearch;

use Models\Category\ChildCategoryModel;
use Models\Category\GrandParentCategoryModel;
use Models\Category\ParentCategoryModel;
use Models\Product\ProductModel;
use Monolog\Logger;
use Shared\Config;
use Shared\Database;

// Index Documents

class Indices extends Search{

    public $product, $store;
    public $child_category, $parent_category, $grand_parent_category;

    function __construct(Config $config, Logger $logger, Database $database, Client $client){
        parent::__construct($config, $logger, $database, $client);

        $this->product = new ProductModel($database);
        $this->store = new ProductModel($database);

        $this->child_category = new ChildCategoryModel($database);
        $this->parent_category = new ParentCategoryModel($database);
        $this->grand_parent_category = new GrandParentCategoryModel($database);
    }

    public function index_products(){

        $this->delete_documents('products');

        $products = $this->product->get();

        $params = [
            'body' => []
        ];

        foreach($products as $product){
            $params['body'][] = [
                'index' => [
                    '_index' => 'products',
                    '_id'    => $product->id
                ]
            ];
        
            $params['body'][] = [
                'id' => (int)$product->id,
                'name'     => $product->name,
                'description' => $product->description,
                'price' => (float)$product->price,
                'weight' => $product->weight,
                'brand' => $product->brand,
                'dietary_info' => $product->dietary_info,
                'allergen_info' => $product->allergen_info,
                'avg_rating' => (float)$product->avg_rating,
                'total_reviews_count' => (float)$product->total_reviews_count,
            ];
        }

        $responses = $this->client->bulk($params);

    }

    public function index_stores(){

        $this->delete_documents('stores');

        $stores = $this->store->get();

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
                'name' => $store->name,
            ];
        }

        $responses = $this->client->bulk($params);
    }

    public function index_categories(){

        $this->delete_documents('categories');

        $categories = array_merge(
            $this->child_category->select_raw(['*', '"child_categories" as "type"'])->get(), 
            $this->parent_category->select_raw(['*', '"parent_categories" as "type"'])->get(),
            $this->grand_parent_category->select_raw(['*', '"grand_parent_categories" as "type"'])->get()
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
              'name' => $category->name,
              'type' => $category->type
            ];
        }

        $responses = $this->client->bulk($params);

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
}

?>