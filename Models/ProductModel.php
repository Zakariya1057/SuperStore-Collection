<?php
// Create Model Class. Use For Inserting Into Databas. Searching And Deleting.
namespace Models;

require_once __DIR__.'/../vendor/autoload.php';

use Shared\Database;
use Shared\Loggers;
use Models\Model;

class ProductModel extends Model {

    public $database, $logger,$product;
    
    function __construct(){
        $this->database = new Database();

        $log = new Loggers();
        $this->logger = $log->logger_handler;

        $this->table("products");

        $fields = [
            "price" => [
                "regex" => "price",
                "nullable" => false,
            ]
        ];

        $this->fields($fields);

    }

}

$product = new ProductModel();

// echo $product->create([
//     'name' => 's',
//     'quantity' => 5
// ]);

$query = $product->whereRaw(['url > 50'])->limit(10)->update(['age' => '2']);
// $query = $product->selectRaw('NOW() as Da')->like(['age' => 'S%','g'=>'%we'])->limit(10)->get();
// echo $product->whereRaw('DATE(t) > 50')->delete();
// $query = $product->where(['store' => 1])->get();

echo $query;

// $product->create(["name" => "Apples", "quantity" => 5]);
// $item = $product->select(["url" => "Some URL"]);

// if($item){
//     echo "Product Found";
// } else {
//     echo "Product Not Found";
// }

?>