<?php
// Create Model Class. Use For Inserting Into Databas. Searching And Deleting.
namespace Models\Product;

class PriceModel {

    public $price,$old_price,$promotion_id,$is_on_sale,$starts_at,$ends_at;

    function __construct(){
        $this->is_on_sale = false;
    }

}

?>