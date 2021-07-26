<?php

namespace Collection\Loblaws\Groceries\Categories;

use Collection\Services\SharedRegionService;
use Collection\Loblaws\Loblaws;
use Collection\Loblaws\Groceries\Products\Products;
use Collection\Loblaws\Services\CategoryService;
use Monolog\Logger;
use Services\ConfigService;
use Services\DatabaseService;
use Services\RememberService;

class Categories extends Loblaws {

    public $grand_parent_categories, $parent_categories, $child_categories;
    public $products;

    function __construct(ConfigService $config_service, Logger $logger, DatabaseService $database_service, RememberService $remember_service=null)
    {
        parent::__construct($config_service, $logger, $database_service, $remember_service);

        $region_service = new SharedRegionService($database_service);
        $category_service = new CategoryService($config_service, $logger, $database_service);

        $products = new Products($config_service, $logger, $database_service, $region_service);
        $child_categories = new ChildCategories($remember_service, $logger, $database_service, $category_service, $products);
        $parent_categories = new ParentCategories($remember_service, $logger, $database_service, $category_service, $child_categories);

        $this->grand_parent_categories = new GrandParentCategories($remember_service, $logger, $database_service, $category_service, $parent_categories);
    }

    public function categories($categories){
        $this->grand_parent_categories->create_categories($categories);
    }

}

?>