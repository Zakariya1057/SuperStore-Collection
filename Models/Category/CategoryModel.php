<?php
// Create Model Class. Use For Inserting Into Databas. Searching And Deleting.
namespace Models\Category;

use Models\Model;

class CategoryModel extends Model {
    public $database,$logger,$product;
    public $id,$name,$site_category_id;
}

?>