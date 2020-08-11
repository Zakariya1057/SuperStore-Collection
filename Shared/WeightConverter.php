<?php

namespace Shared;

class WeightConverter {

    public function grams($weight){
        //Convert kilograms to grams
        preg_match('/kg/i',$weight,$matches);

        if($matches){
            $weight = str_replace('kg','',$weight);
            $weight = number_format($weight / 1000, 2);
        }

        return $weight;
    }

}

?>