<?php
namespace Admin;
use Psr\Container\ContainerInterface as Container;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

define('EXCHANGE_API', $_ENV["EXCHANGE_API"]);

ini_set('max_execution_time', 2400);

class Visitors
{
    /** @var \Slim\Container
     */
    protected $container;

    public function __construct(Container $container)
    {
       $this->sql = $container->get('mysql');
    }

    public function allVisitorsGet($request, $response, $args)
    {
      $data = $this->sql->query(
        'SELECT
          s.id,
          s.country,
          s.continent,
          s.userId,
          s.ip,
          s.isMobile,
          s.event,
          s.path,
          s.platform,
          s.browser,
          s.timestamp
        FROM
          analytics_visitors s
        WHERE
          s.timestamp >= NOW() - INTERVAL 30 DAY
        ORDER BY
            s.timestamp DESC',
      []
      );
      return emit($response, $data);
    }

    public function mapVisitorsGet($request, $response, $args)
{
    $data = $this->sql->query(
        'SELECT
            s.country,
            COUNT(*) AS visitor_count
        FROM
            analytics_visitors s
        WHERE
            s.timestamp >= NOW() - INTERVAL 30 DAY
        GROUP BY
            s.country
        ORDER BY
            visitor_count DESC',
        []
    );

    foreach ($data as &$d) {
      $d['country'] = ucwords(strtolower($d['country']));
    }

    return emit($response, $data);
}

}

 ?>




