<?php
namespace Admin;
use Psr\Container\ContainerInterface as Container;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

define('EXCHANGE_API', $_ENV["EXCHANGE_API"]);

ini_set('max_execution_time', 2400);

class Clicks
{
    /** @var \Slim\Container
     */
    protected $container;

    public function __construct(Container $container)
    {
       $this->sql = $container->get('mysql');
    }

    public function clicksCoursesGet($request, $response, $args)
    {
      $data = $this->sql->query(
        'SELECT
          s.id,
          s.courseId,
          s.providerId,
          s.userId,
          s.timestamp
        FROM
            analytics_clicks_courses s
        ORDER BY
            s.timestamp DESC
        LIMIT 1000',
      []
      );
      return emit($response, $data);
    }

     public function clicksProvidersGet($request, $response, $args)
    {
      $data = $this->sql->query(
        'SELECT
          s.id,
          s.providerId,
          s.userId,
          s.timestamp
        FROM
            analytics_clicks_providers s
        ORDER BY
            s.timestamp DESC
        LIMIT 1000',
      []
      );
      return emit($response, $data);
    }


}

 ?>




