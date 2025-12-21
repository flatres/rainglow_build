<?php
namespace Dependency\Databases;


class LearnFlow extends \Dependency\MySql
{

  public function __construct() {

    $this->connect($_ENV['DB_NAME']);

  }

}

 ?>
