<?php

function searchArray(array $array, $needle, $field) {
   foreach ($array as $val) {
       if ($val[$field] === $needle) {
           return $val;
       }
   }
   return null;
}
