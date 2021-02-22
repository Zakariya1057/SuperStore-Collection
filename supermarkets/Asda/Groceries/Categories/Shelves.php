<?php

namespace Supermarkets\Asda\Groceries\Categories;

use Monolog\Logger;
use Services\Config;
use Services\Database;
use Services\Remember;
use Supermarkets\Asda\Asda;
use Supermarkets\Asda\Groceries\Products\Products;

class Shelves extends Asda {

    function __construct(Config $config, Logger $logger, Database $database, Remember $remember=null)
    {
        parent::__construct($config,$logger,$database,$remember);
    }

    public function details($shelf){
        // Get list of all product sold on shelf. Insert new products
        $products = $this->shelf_products($shelf->site_category_id);

        $this->logger->notice('Found '.count($products).' Products For Category: '.$shelf->name);

        $last_product_index = $this->remember->get('product_index') ?? 0;
        
        $products = array_slice($products,$last_product_index);

        $first_product = $products[0];

        $this->logger->notice("Starting With Product: [$last_product_index] $first_product");

        //Loop through and insert into database
        foreach($products as $index => $product_item){

            $this->remember->set('product_index', $index + $last_product_index);

            $product = new Products($this->config,$this->logger,$this->database,$this->remember);
            $product->product($product_item,$shelf->grand_parent_category_id, $shelf->parent_category_id,$shelf->id);

            //Between Each Products. Wait 1 Second
            sleep(1);
            
        }

        $this->remember->set('product_index',0);
    }

    public function shelf_products($site_shelf_id){
        //Return an array of products with details. To be then be inserted into database
        $products = [];

        $shelf_endpoint = $this->endpoints->shelves . $site_shelf_id;
        $this->logger->debug("Shelf: $site_shelf_id");

        if($this->env == 'dev'){
            $shelf_results = file_get_contents(__DIR__."/../../Data/Asda/Shelf.json");
        } else {
            $shelf_results = $this->request->request($shelf_endpoint);
        }
        
        $shelf_data = $this->request->parse_json($shelf_results);

        foreach($shelf_data->contents[0] as $header => $content_parts){

            if(strtolower($header) == "maincontent"){

                foreach($content_parts as $content_part){
                    if(strtolower($content_part->{'@type'}) == "aislecontentholder" ){

                        $records = (object)$content_part->dynamicSlot->contents[0]->mainContent[0]->records;

                        foreach($records as $record){
                            $attributes =  $record->{'attributes'};
                            $product_id = $attributes->{'sku.repositoryId'}[0];
                            $name = $attributes->{'sku.displayName'}[0];
                            
                            $products[] = $product_id;

                        }

                    }
                }

            }
            
        }

        return $products;

    }

}

?>