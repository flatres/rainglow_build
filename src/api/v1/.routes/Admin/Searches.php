<?php
namespace Admin;
use Psr\Container\ContainerInterface as Container;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

define('EXCHANGE_API', $_ENV["EXCHANGE_API"]);

ini_set('max_execution_time', 2400);

class Searches
{
    /** @var \Slim\Container
     */
    protected $container;

    public function __construct(Container $container)
    {
       $this->sql = $container->get('mysql');
    }

    public function searchesCoursesGet($request, $response, $args)
    {
      $data = $this->sql->query(
        'SELECT
          s.id,
          s.countryId,
          c.name AS countryName,
          s.categoryId,
          cat.name AS categoryName,
          s.userId,
          s.search,
          s.timestamp
        FROM
            analytics_searches_courses s
        LEFT JOIN
            countries c ON s.countryId = c.id
        LEFT JOIN
            categories cat ON s.categoryId = cat.id
        ORDER BY
            s.timestamp DESC
        LIMIT 1000',
      []
      );
      return emit($response, $data);
    }

     public function searchesProvidersGet($request, $response, $args)
    {
      $data = $this->sql->query(
        'SELECT
          s.id,
          s.countryId,
          c.name AS countryName,
          s.categoryId,
          cat.name AS categoryName,
          s.userId,
          s.search,
          s.timestamp
        FROM
            analytics_searches_providers s
        LEFT JOIN
            countries c ON s.countryId = c.id
        LEFT JOIN
            categories cat ON s.categoryId = cat.id
        ORDER BY
            s.timestamp DESC
        LIMIT 1000',
      []
      );
      return emit($response, $data);
    }


}

 ?>




