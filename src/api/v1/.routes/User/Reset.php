<?php
namespace User;

use Psr\Container\ContainerInterface as Container;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

class Reset
{
    protected $container;

    public function __construct(Container $container)
    {
       $this->sql= $container->get('mysql');
       $this->container = $container;
    }


    public function resetPost($request, $response, $args)
    {       // reading json params
      $data = $request->getParsedBody();
      $sql = $this->sql;
      $email = strtolower($data['email']);

      if (strlen($email) === 0) {
        emitError($response, 403, 'email address required');
      } //return error

      $checkData = $sql->single('usr_details', 'id', 'email=?', [$email]);

      if ($checkData) {
        //send reset email
        $hash = bin2hex(random_bytes(32));
        $userId = $checkData['id'];
        $timestamp = date("Y-m-d H:i:s");
        $sql->insert('usr_reset', 'user_id, hash, timestamp', [$userId, $hash, $timestamp]);
        $this->sendResetEmail($userId, $hash);
      } else {
      }
      return emit($response, true);
    }

    private function sendResetEmail($userId, $hash) {
      $user = $this->sql->select('usr_details', 'email, name', 'id = ?', [$userId])[0] ?? null;
      if (!$user) return false;
      $email = new \Utilities\Postmark\Emails\User\ResetEmail($user['email'], $user['name'], $hash);
    }

    public function passwordPost($request, $response, $args)
    {
      $data = $request->getParsedBody();
      $hash = $data['hash'];
      $password = $data['password'];

      $passHash = new \User\Tools\PassHash();
      $passwordHash = $passHash->hash($password);

      //see if hash exists and if it has expired (24 hrs)
      $hashData = $this->sql->select('usr_reset', 'user_id, timestamp', 'hash=?', [$hash])[0] ?? null;

      // if (!$hashData) return emitError($response, 400, 'Link has expired');

      $ageInSecs = time() - strtotime($hashData['timestamp']);
      // echo $ageInSecs; exit();
      if ($ageInSecs > 24*60*60) return emitError($response, 400, 'Link has expired');

      $userId = $hashData['user_id'];
      // $this->sql->delete('usr_reset', 'user_id=?', [$userId]);
      $this->sql->update('usr_details', 'password_hash=?, activated=?', 'id=?', [$passwordHash, 1, $userId]);

      // return loginObject
      $loginObject = (new \User\Login($this->container))->loginReturnObject($userId);

      return emit($response, $loginObject);
    }

}

 ?>
