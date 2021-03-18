<?php

namespace Interfaces;

use Models\Store\StoreModel;

interface StoreInterface {
    public function store_details($site_store_id, $url = null): ?StoreModel;
}

?>