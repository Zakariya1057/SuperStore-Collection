<?php
// Create Model Class. Use For Inserting Into Databas. Searching And Deleting.
namespace Models;

require_once __DIR__.'/../vendor/autoload.php';

use Shared\Database;
use Shared\Loggers;
use Models\Model;

//Store Name, Logo, Description, etc.

// Cat Id, Name, Level(0,1,2),parent_id
// Cat Id, Name, Level(1,2,3),parent_id

//Select * from parent_categories inner join child_categories on child_categories.parent_id = parent_categories.id inner join products on products.parent_id = child_categories.id where store_type=1
//SELECT * FROM categories C1 inner join categories C2 on C1.parent_id = C2.id inner join categories C3 on C2.parent_id = C3.id

//Grandparent category, parent category, child category

class CategoryModel extends Model {
    public $database,$logger,$product;
    public $id,$name,$site_category_id;
}

?>