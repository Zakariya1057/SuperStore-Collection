<?php
 
namespace Models\Category;

use Models\Model;
use Services\DatabaseService;

class CategoryModel extends Model {
    public $database_service,$logger,$product;
    public $id,$name,$site_category_id;
}

?>