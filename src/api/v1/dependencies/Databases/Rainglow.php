<?php
namespace Dependency\Databases;


class Rainglow extends \Dependency\MySql
{

  public function __construct() {

    $this->connect($_ENV['DB_NAME']);

  }

}

 ?>
