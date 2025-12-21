<?php
namespace Analytics;
use Psr\Container\ContainerInterface as Container;

class Analytics
{
    protected $container;

    public function __construct(Container $container)
    {
       $this->sql= $container->get('mysql');
       $this->container = $container;
    }

    public function providerClickPost($request, $response, $args)
    {
      global $userId;
      $providerId = $args['id'];
      $this->sql->insert('analytics_clicks_providers', 'providerId, userId', [$providerId, $userId]);
      return emit($response, [true]);
    }

    public function courseClickPost($request, $response, $args)
    {
      global $userId;
      $courseId = $args['id'];
      $course = $this->sql->single('courses', 'providerId', 'id=?', [$courseId]);
      if (!$course) return emit($response, [false]);
      $providerId = $course['providerId'];
      $this->sql->insert('analytics_clicks_courses', 'courseId, providerId, userId', [$courseId, $providerId, $userId]);
      return emit($response, [true]);
    }

     public function homepageClickPost($request, $response, $args)
    {
      global $userId;
      $providerId = $args['id'];

      $this->sql->insert('favourites_providers', 'providerId, userId', [$providerId, $userId]);
      return emit($response, [true]);
    }

     public function pagePost($request, $response, $args)
    {
      $userId = null;
      // $auth =  $request->getHeader('Authorization')[0];
      // $auth = str_replace('Bearer ', '', $auth);
      // $d= $this->sql->single('usr_sessions', 'user_id', 'token=? AND expired=0', [$auth]);
      // if ($d) $userId = $d['user_id'];
      global $userId;
      $data = $request->getParsedBody();
      // $ip = $_SERVER['REMOTE_ADDR'];

      $ip = $_SERVER['HTTP_X_REAL_IP'] ?? $_ENV['HTTP_X_REAL_IP'];
      $continent = $_SERVER['HTTP_X_FORWARDED_CONTINENT'] ?? $_ENV['HTTP_X_FORWARDED_CONTINENT'];
      $country = $_SERVER['HTTP_X_FORWARDED_COUNTRY'] ?? $_ENV['HTTP_X_FORWARDED_COUNTRY'];
      $data['isMobile'] = $data['isMobile'] ?? false;

      $this->sql->insert(
        'analytics_visitors',
        'userId, ip, isMobile, continent, country, event, platform, path, browser',
        [
          $userId,
          $ip,
          $data['isMobile'] ? 1 : 0,
          $continent,
          $country,
          $data['event'],
          $data['platform'],
          $data['path'],
          $data['browser']
        ]);
      return emit($response, [true]);
    }

}

 ?>
