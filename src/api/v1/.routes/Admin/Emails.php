<?php
namespace Admin;
use Psr\Container\ContainerInterface as Container;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

define('EXCHANGE_API', $_ENV["EXCHANGE_API"]);

ini_set('max_execution_time', 2400);

class Emails
{
    /** @var \Slim\Container
     */
    protected $container;

    public function __construct(Container $container)
    {
       $this->sql = $container->get('mysql');
    }

    public function allEmailsGet($request, $response, $args)
    {
      $data = $this->sql->query(
        'SELECT
          s.id,
          s.toEmail,
          s.subject,
          s.tag,
          s.timestamp
        FROM
          analytics_emails s
        WHERE
          s.timestamp >= NOW() - INTERVAL 30 DAY
        ORDER BY
            s.timestamp DESC',
      []
      );
      return emit($response, $data);
    }

}

 ?>




