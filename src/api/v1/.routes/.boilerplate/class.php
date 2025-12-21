<?php

/**
 * Description

 * Usage:

 */
namespace NAME;
use Psr\Container\ContainerInterface as Container;
class CLASS
{
    protected $container;

    public function __construct(Container $container)
    {
       $this->ada = $container->get('ada');
       $this->adaModules = $container->get('adaModules');
       $this->isams = $container->get('isams');
    }

// ROUTE -----------------------------------------------------------------------------
    public function ROUTEGet($request, $response, $args)
    {
      return emit($response, $this->adaModules->select('TABLE', '*'));
    }

    public function ROUTEPost($request, $response)
    {
      $data = $request->getParsedBody();
      $data['id'] = $this->adaModules->insertObject('TABLE', $data);
      return emit($response, $data);
    }

    public function ROUTELocationsPut($request, $response)
    {
      $data = $request->getParsedBody();
      return emit($response, $this->adaModules->updateObject('TABLE', $data, 'id'));
    }

    public function ROUTEDelete($request, $response, $args)
    {
      return emit($response, $this->adaModules->delete('TABLE', 'id=?', array($args['id'])));
    }

}
