<?php

namespace Stores\Asda;

use Exception;
use Models\Product\ProductModel;
use Shared\Request;
use Shared\Requests;

class AsdaShelves extends Asda {

    function __construct($config,$logger,$database)
    {
        parent::__construct($config,$logger,$database);
    }

    public function details($shelf){
        // Get list of all product sold on shelf. Insert new products
        $products = $this->shelf_products($shelf->site_category_id);

        $this->logger->notice('Found '.count($products).' Products For Category: '.$shelf->name);

        //Loop through and insert into database
        foreach($products as $product_item){
            $product = new AsdaProducts($this->config,$this->logger,$this->database);
            $product->product($product_item,$shelf->id);
        }
    }

    public function shelf_products($site_shelf_id){
        //Return an array of products with details. To be then be inserted into database
        $products = [];

        $shelf_endpoint = $this->endpoints->shelves . $site_shelf_id;
        $this->logger->debug("Shelf: $site_shelf_id");

        if($this->env == "dev"){
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

                            // $this->logger->debug("Name: $name \tProduct ID: $product_id");
                            // $product = $this->product($product_id);

                            $products[] = $product_id;
                            
                        }

                    }
                }

            }
            
        }
        
        // file_put_contents(__DIR__.'/../../Data/Asda/Products_List.json',json_encode($products));

        return $products;

    }

}

?>