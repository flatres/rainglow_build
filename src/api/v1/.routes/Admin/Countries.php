<?php
namespace Admin;
use Psr\Container\ContainerInterface as Container;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

define('EXCHANGE_API', $_ENV["EXCHANGE_API"]);

ini_set('max_execution_time', 2400);

class Countries
{
    /** @var \Slim\Container
     */
    protected $container;

    public function __construct(Container $container)
    {
       $this->sql = $container->get('mysql');
    }

    public function countriesGet($request, $response, $args)
    {
      $data = [];
      $data = $this->sql->query(
          'SELECT
            c.id AS id,
            c.name AS name,
            c.isoCode,
            c.isoCurrency,
            c.currencySymbol,
            c.isActive AS isActive,
            COUNT(DISTINCT cc.id) AS providerCount
        FROM
            countries c
        LEFT JOIN
            countries_providers cc ON c.id = cc.countryId
        GROUP BY
            c.id, c.name
        ORDER BY c.name ASC', []);
      return emit($response, $data);
    }

    public function countryPut($request, $response, $args)
    {
      $cat = $request->getParsedBody();
      $cnt = $this->sql->single('countries', 'isActive', 'id=?', [$cat['id']]);

      if ($cnt['isActive'] !== $cat['isActive']) {
        // then could be changing active state from pending list so set pending = 0
        $this->sql->update(
          'countries',
          'isActive=?',
          'id=?',
          [$cat['isActive'], $cat['id']]
        );
      }
      return emit($response, [true]);
    }
}

 ?>




