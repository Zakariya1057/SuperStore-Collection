<?php

namespace Collection\Supermarkets\Canadian_Superstore\Groceries\Recommended;

use Exception;
use Models\Category\ChildCategoryModel;
use Models\Product\ProductModel;
use Models\Product\RecommendedModel;
use Collection\Supermarkets\Canadian_Superstore\CanadianSuperstore;
use Collection\Supermarkets\Canadian_Superstore\Groceries\Products\Products;
use Collection\Supermarkets\Canadian_Superstore\Services\ProductService;
use Monolog\Logger;
use Services\ConfigService;
use Services\DatabaseService;
use Services\RememberService;

class Recommended extends CanadianSuperstore {

    private $product_model, $category_model;
    private $product_service;

    private $product;

    function __construct(ConfigService $config_service, Logger $logger, DatabaseService $database_service, RememberService $remember_service=null)
    {
        parent::__construct($config_service,$logger,$database_service,$remember_service);

        $this->product_model = new ProductModel($this->database_service);
        $this->category_model = new ChildCategoryModel($this->database_service);

        $this->product = new Products($this->config_service,$this->logger,$this->database_service,$this->remember_service);

        $this->product_service = new ProductService($this->config_service, $this->logger, $this->database_service);
    }

    public function create_recommended(){

        // Loop through all product in database without related products and set their related products.
        $this->logger->notice('------ Product Recommended Start ---------');

         // After recommended product run additonal product will be created. These will require an addtional run.
        while(true){

            $products_without_recommended = $this->product_model->select(['id','site_product_id','name'])->where(['store_type_id' => $this->store_type_id, 'recommended_searched' => null])->order_by('id','DESC')->get();
        
            if($products_without_recommended){
    
                $product_count = count($products_without_recommended);
    
                $this->logger->debug("Found $product_count Products Without Recommended");
        
                foreach($products_without_recommended as $product){
                    $name = $product->name;
                    $product_id = $product->id;
                    $site_product_id = $product->site_product_id;
        
                    $this->logger->debug("New Product To Find Recommended Item: [$product_id] $name");
        
                    $this->database_service->start_transaction();
    
                    $this->product_recommended($product_id, $site_product_id);
    
                    $this->database_service->commit_transaction();
                }
    
            } else {
                $this->logger->notice('No Product Without Recommended Found');
                break;
            }

        }

        $this->logger->notice('------ Product Recommended Complete ---------');

    }

    public function product_recommended($product_id, $site_product_id){

        $this->logger->debug('Finding Recommended Products For: ' . $product_id);

        // Get all recommended for given product
        $recommendation_endpoint = $this->endpoints->recommended. $site_product_id . '/recommendations';

        if($this->env == 'dev'){
            $recommended_response = file_get_contents(__DIR__."/../../../../data/Canadian_Superstore/Recommendations.json");
        } else {
            try {
                $recommended_response = $this->request_service->request($recommendation_endpoint, 'GET', [] , ['Site-Banner' => 'superstore'], 300, 1);
            } catch(Exception $e){
                $recommended_response = '{}';
                $this->logger->error('Failed To Find Recommended Products: '. $e->getMessage());
            }
            
        }

        $product_ids = [];

        $recommended_data = $this->request_service->parse_json($recommended_response);

        if(!is_null($recommended_data)){
            if(property_exists($recommended_data, 'relatedProducts')){
                $recommended_products = $recommended_data->relatedProducts;
    
                foreach($recommended_products as $product_data){
                    $recommended = new RecommendedModel($this->database_service);
                    $product_code = $product_data->code;
    
                    $product_results = $this->product_model->where(['store_type_id' => $this->store_type_id, 'site_product_id' => $product_code])->get()[0] ?? null;
    
                    if(is_null($product_results)){
                        // New product, check to see if any existing categories found. For each found category, create it under.
    
                        $product_item = $this->product->product_details($product_code);
    
                        $this->logger->debug('New Product Found. Find Correct Category');
    
                        if(is_null($product_item)){
                            $this->logger->error('Product Not Found. Skipping.');
                            continue;
                        }
    
                        if(count($product_item->categories) == 0){
                            $this->logger->error('Product no categories set. Skipping.');
                            continue;
                        }
    
                        $category_results = $this->category_model
                        ->select([
                            'child_categories.id as id', 
                            'parent_categories.id as parent_category_id', 
                            'parent_categories.parent_category_id as grand_parent_category_id'
                        ])
                        ->where_in('child_categories.site_category_id', $product_item->categories)
                        ->where(['child_categories.store_type_id' => $this->store_type_id])
                        ->join('parent_categories', 'parent_categories.id', 'child_categories.parent_category_id')
                        ->get()[0] ?? null;
    
                        if(!is_null($category_results)){
    
                            $this->logger->debug('Product Category Found In Database. Creating Product');
    
                            // Creting Product
                            $created_product_id = $this->product->create_product($product_code, $category_results, '', $product_item);
    
                            $this->logger->debug('Setting Product Recommended');
    
                            // Setting Recommened
                            $recommended->product_id = $product_id;
                            $recommended->recommended_product_id = $created_product_id;
                            $recommended->insert_ignore = true;
                            $recommended->save();
    
                            $product_ids[] = $created_product_id;
                        } else {
                            $this->logger->error('No category found. Skipping product item for now.');
                        }
    
                    } else {
                        // Existing product, just set recommened.
                        $this->logger->debug('Product Found. Setting Recommened.');
                        $recommended->product_id = $product_id;
                        $recommended->recommended_product_id = $product_results->id;
                        $recommended->insert_ignore = true;
                        $recommended->save();
    
                        $product_ids[] = $product_results->id;
                    }
                    
                }
    
                if(count($product_ids) > 0){
                    // Delete older recommended not found.
                    $recommended->where(['product_id' => $product_id])->where_not_in('recommended_product_id',$product_ids)->delete();
                }
    
    
            } else {
                $this->logger->error('No Related Products Found: ' . $site_product_id);
            }
        }


        $this->product_model->where(['id' => $product_id])->update(['recommended_searched' => 1]);

    }
    
}

?>