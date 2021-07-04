<?php
 
namespace Models\ScriptHistory;

use Models\Model;
use Services\DatabaseService;

class ScriptHistory extends Model {
    
    public $company_id, $grand_parent_category_index, $parent_category_index, $child_category_index, $product_index;

    function __construct(DatabaseService $database_service=null){

        parent::__construct($database_service);

        $this->table('script_histories');

        $fields = [

            'company_id' => [ 
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
            
        ];

        $this->fields($fields);

    }

}

?>