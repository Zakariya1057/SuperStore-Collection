<?php

namespace Collection\Loblaws\Groceries\Categories;

use Exception;
use Models\Category\CategoryProductModel;
use Models\Category\ChildCategoryModel;
use Collection\Loblaws\Groceries\Products\Products;
use Collection\Loblaws\Services\CategoryService;
use Monolog\Logger;
use Services\DatabaseService;
use Services\RememberService;

class ChildCategories {

    private $logger, $database_service;

    private $remember_service, $category_service;
    private $products;

    public function __construct(RememberService $remember_service, Logger $logger, DatabaseService $database_service, CategoryService $category_service, Products $products){
        $this->remember_service = $remember_service;
        $this->category_service = $category_service;

        $this->products = $products;

        $this->logger = $logger;
        $this->database_service = $database_service;
    }

    public function create_category($parent_category_model, $parent_category){

        $categories = $parent_category->child_categories;

        $last_category_index = $this->remember_service->get('child_category_index') ?? 0;

        $grand_parent_category_id = $parent_category->parent_category_id;
        
        $categories_list = array_slice($categories, $last_category_index);

        if(count($categories_list) != 0){

            $first_category = (object)$categories_list[0];

            $this->logger->debug("Starting With Child Category: [$last_category_index] " . $first_category->name);
    
            foreach($categories_list as $index => $child_category){

                $category_index = $index + $last_category_index;

                $child_category = (object)$child_category;
                $child_category->parent_category_id = $parent_category_model->id;
                $child_category_details = $this->category_service->select_category($child_category, 'child', $category_index);

                $child_category_details->number = $child_category->number;
                $child_category_details->grand_parent_category_id = $grand_parent_category_id;

                $this->remember_service->set('child_category_index', $category_index);
                $this->category_products($child_category_details);
            }

            $this->remember_service->set('child_category_index',0);

        }

    }


    public function category_products($category_details){

        // Get list of all product sold on shelf. Insert new products
        $products = $this->category_service->category_products($category_details);

        $product_count = count($products);
        
        $this->logger->notice("Found $product_count Products For Category: {$category_details->name}");

        if($product_count > 0){

            $last_product_index = $this->remember_service->get('product_index') ?? 0;
        
            $products = array_slice($products, $last_product_index);
    
            //Loop through and insert into database
            foreach($products as $index => $site_product_id){
                $this->remember_service->set('product_index', $index + $last_product_index);
    
                $this->products->create_product($site_product_id, $category_details);
    
                // Between Each Products. Wait 1 Second
                sleep(1);
                
            }

        } else {
            $this->logger->info('No Products Found For Category: '. $category_details->id);

            $product_categories = new CategoryProductModel($this->database_service);
            $products_count = count($product_categories->where(['child_category_id' => $category_details->id])->get());

            if($products_count == 0){
                $this->logger->debug('No Products Found For Matching Product Categories. Deleting Child Category');
                // Check if no products for category
                $category_model = new ChildCategoryModel($this->database_service);
                $category_model->where(['id' => $category_details->id])->delete();
            } else {
                $this->logger->debug('Products Found In Database For Category. Not Deleting Child Category');
            }

        }

        $this->remember_service->set('product_index', 0);
    }

}

?>