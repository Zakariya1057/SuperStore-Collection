<?php

namespace Interfaces;

use Models\Product\ProductModel;

interface ProductInterface {
    public function product_details($site_product_id, $ignore_image=false): ?ProductModel;
}

?>