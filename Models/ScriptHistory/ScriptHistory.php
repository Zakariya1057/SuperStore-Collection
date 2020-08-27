<?php
// Create Model Class. Use For Inserting Into Databas. Searching And Deleting.
namespace Models\ScriptHistory;

use Models\Model;

//id,site_name, grand_parent_category_index, parent_category_index, child_category_index, product_index, error_file, error_message, host, created_at, updated_at

class ScriptHistory extends Model {

    public $database, $logger,$product;
    
    public $store_type_id, $grand_parent_category_index, $parent_category_index, $child_category_index, $product_index, $error_file, $error_message,$error_line_number;
    function __construct($database=null){

        parent::__construct($database);

        $this->table("script_histories");

        $fields = [

            'store_type_id' => [ 
                'type' => 'int'
            ],
            
            'grand_parent_category_index' => [
                'nullable'=> true,
                'type' => 'int'
            ],
            'parent_category_index' => [
                'nullable'=> true,
                'type' => 'int'
            ],
            'child_category_index' => [
                'nullable'=> true,
                'type' => 'int'
            ],

            'product_index' => [
                'nullable'=> true,
                'type' => 'int'
            ],

            'error_file' => [
                'nullable'=> true,
            ],
            'error_message' => [
                'nullable'=> true,
            ],
            'error_line_number' => [
                'nullable'=> true,
                'type' => 'int'
            ],
            
        ];

        $this->fields($fields);

    }

}

?>