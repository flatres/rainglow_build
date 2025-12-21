<?php

//takes an associative array and saves it to an object
//used when returning a record from a database that needs to be saved to the current object
function saveToObject(array $record, &$object) {
  foreach($record as $key => $value) {
    $object->{$key} = $value;
  }
}

?>