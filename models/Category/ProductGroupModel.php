<?php
 
namespace Models\Category;

use Models\Model;
use Services\DatabaseService;

class ProductGroupModel extends Model {

    public $name, $child_category_id, $site_product_group_id, $company_id;

    function __construct(DatabaseService $database_service=null){

        parent::__construct($database_service);

        $this->table('product_groups');

        $fields = [
            'name' => [],
            'child_category_id' => [],
            'company_id' => [],
            'site_product_group_id' => []
        ];

        $this->fields($fields);

    }

}

?>