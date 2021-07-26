<?php

namespace Interfaces;

use Models\Store\StoreModel;

interface StoreInterface {
    public function store_details(?string $site_store_id, int $supermarket_chain_id, String $supermarket_url): ?StoreModel;
}

?>