<?php
namespace Auth;
use Psr\Container\ContainerInterface as Container;
class Bug
{
    protected $container;


    public function __construct(Container $container)
    {
       $this->sql= $container->get('mysql');
    }


    public function report($request, $response, $args)
    {
      global $userId;
      $data = $request->getParsedBody();
      $email = $this->sql->single('usr_details', 'email', 'id=?', [$userId])['email'];

      $cc = [
        $email,
        'learnflow@fire.fundersclub.com'
      ];

      $email = new \Utilities\Postmark\Emails\Bugs\BugEmail(
        'learnflow@fire.fundersclub.com',
        $email,
        'Issue: ' . $data['subject'],
        $data['path'],
        $email . ' : ' . $data['message']);

      return emit($response, $data);

   }


}

 ?>
