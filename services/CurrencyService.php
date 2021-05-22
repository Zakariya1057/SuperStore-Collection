<?php

namespace Services;

use Exception;

class CurrencyService {

    public function get_currency_symbol($currency_name){
        $currency_name = strtolower($currency_name);

        $currencies = [
            'canadian dollars' => '$',
            'pounds' => '£',
        ];

        return $currencies[$currency_name];
    }
}

?>