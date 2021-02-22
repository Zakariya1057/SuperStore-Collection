<?php

namespace Supermarkets\Asda\Groceries\Categories;

use Models\Category\CategoryProductModel;
use Models\Category\ChildCategoryModel;

use Monolog\Logger;
use Services\Config;
use Services\Database;
use Services\Remember;
class Aisles extends Categories {

    public $shelf;

    function __construct(Config $config, Logger $logger, Database $database, Remember $remember=null)
    {
        parent::__construct($config,$logger,$database,$remember);
        $this->shelf = new Shelves($config,$logger,$database,$remember);
    }

    public function create($aisle,$grand_parent_category_id,$parent_category_id){
        $aisle->parent_category_id = $parent_category_id;

        $aisle_details = $this->select_category($aisle,"child");
        $this->logger->notice("--- Aisle: $aisle_details->name");

        if($this->exclude_service->exclude_category($aisle_details->name)){
            $this->logger->error('Exluding Haram Category');
            return;
        }

        $aisle_details->grand_parent_category_id = $grand_parent_category_id;
        $aisle_details->parent_category_id = $parent_category_id;

        $this->shelf->details($aisle_details);

        // If no products for shelves found then delete this aisle.
        // $category_products = new CategoryProductModel($this->database);
        // $products_count = count((array)$category_products->where(['child_category_id' => $aisle_details->id])->get());

        // if($products_count == 0){
        //     $this->logger->notice('Deleting Aisle Item '. $aisle_details->id.'. No Products Using It');

        //     $aisle = new ChildCategoryModel($this->database);
        //     $aisle->where(['id' => $aisle_details->id])->delete();
        // }

    }

}

?>