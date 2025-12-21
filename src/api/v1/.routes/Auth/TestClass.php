<?php
namespace Auth;
use Psr\Container\ContainerInterface as Container;

class TestClass
{

    protected $container;

    public function __construct(Container $container) {

       $this->container = $container;

       $this->db =  $container->get('mysql');
    }

    public function testGet($request, $response, $args) {

      $data = array('message'=>'it worked');
      return emit($response, $data);
    }
}

 ?>
