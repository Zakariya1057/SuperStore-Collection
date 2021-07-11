<?php
 
namespace Models\Store;

use Models\Model;
use Services\DatabaseService;

class CompanyModel extends Model {
    
    public $id, $name, $currency, $description, $large_logo, $small_logo, $user_id;
    
    function __construct(DatabaseService $database_service=null){

        parent::__construct($database_service);

        $this->table('companies');

        $fields = [
            'name' => []
        ];

        $this->fields($fields);

    }

}

?>