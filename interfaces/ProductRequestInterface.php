<?php

namespace Interfaces;

interface ProductRequestInterface {
    public function request_product($site_product_id, $request_type = null);
}

?>