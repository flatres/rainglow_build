<?php
namespace User;
use Psr\Container\ContainerInterface as Container;

class Notifications
{
    /** @var \Slim\Container
     */

    protected $container;

    public function __construct(Container $container)
    {
       $this->sql= $container->get('mysql');
    }


    public function notificationsGet($request, $response, $args)
    {
      global $userId;
      $notifications = (new \Trips\Tools\Notifications($this->sql))->byUserId($userId);
      return emit($response, $notifications);
    }

    public function notificationAsReadPut($request, $response, $args) {
      $this->sql->update('usr_notifications', 'hasRead=1', 'id=?', [$args['id']]);
      return emit($response, true);
    }

}

 ?>
