<?php

namespace Collection\Supermarkets\Asda\Groceries\Recommended;

use Exception;

use Collection\Supermarkets\Asda\Asda;

use Monolog\Logger;
use Services\ConfigService;
use Services\DatabaseService;
use Services\RememberService;

use Collection\Services\SharedProductService;
use Collection\Supermarkets\Asda\Services\RecommendedService;

use Models\Product\ProductModel;
use Models\Category\ChildCategoryModel;

class Recommended extends Asda {

    public $product_model, $recommended_service, $product_service;

    function __construct(ConfigService $config_service, Logger $logger, DatabaseService $database_service, RememberService $remember_service=null)
    {
        parent::__construct($config_service, $logger, $database_service, $remember_service);

        $this->product_model = new ProductModel($this->database_service);
        $this->recommended_service = new RecommendedService($config_service,$logger,$database_service,$remember_service);
        $this->product_service = new SharedProductService($database_service);
        $this->category_model = new ChildCategoryModel($this->database_service);
    }

    public function all_recommended_products(){
        //Loop through all product in database without related products and set their related products.
        $this->logger->notice('------ Product Recommended Start ---------');
        
        // After recommended product run additonal product will be created. These will require an addtional run.
        while(true){

            $products_without_recommended = $this->product_model->select(['id','site_product_id','name'])->where(['store_type_id' => $this->store_type_id, 'recommended_searched' => null])->order_by('id','ASC')->get();
            
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

        $recommended_data = $this->recommended_service->request_recommended($site_product_id);

        if(property_exists($recommended_data, 'results') && count($recommended_data->results) > 0){
        
            $results = $recommended_data->results[0]->items;

            $product_ids = [];

            foreach($results as $item){

                if($item->id == 1000197472184){
                    // ASDA Grower's Selection 7 Bananas
                    $this->logger->debug('Ignore banana recommened item. Return on unrelated random items so ignore from here on out');
                    continue;
                }

                $site_product_id = $item->id;
                $site_category_id = $item->aisleId;

                if($recommended_product_id = $this->product_service->product_exists($site_product_id, $this->store_type_id)){
                    $this->recommended_service->create($product_id, $recommended_product_id);
                    $product_ids[] = $recommended_product_id;
                } else {
                    $this->logger->warning('Similar Product Not Found In Database. Creating The Product, Then Setting As Recommened');

                    $category_results = $this->category_model
                    ->select([
                        'child_categories.id as id', 
                        'parent_categories.id as parent_category_id', 
                        'parent_categories.parent_category_id as grand_parent_category_id'
                    ])
                    ->like(['child_categories.site_category_id' => "%$site_category_id"])
                    ->where(['child_categories.store_type_id' => $this->store_type_id])
                    ->join('parent_categories', 'parent_categories.id', 'child_categories.parent_category_id')
                    ->first();

                    if(!is_null($category_results)){

                        $this->logger->debug('Product Category Found In Database. Creating Product');

                        // Creting Product
                        $created_recommended_product_id = $this->product->create_product($site_product_id, $category_results);

                        $this->logger->debug('Setting Product Recommended');

                        $this->recommended_service->create($product_id, $created_recommended_product_id);
                        $product_ids[] = $recommended_product_id;
                    } else {
                        $this->logger->error('No category found. Skipping product item for now.');
                    }

                }
            }

            $this->recommended_service->delete_not_found($product_id, $product_ids);

        }

        $this->recommended_service->recommended_complete($product_id);

    }

}

?>