<?php
namespace User;
use Psr\Container\ContainerInterface as Container;

class Signup
{
    protected $container;

    public function __construct(Container $container)
    {
       $this->sql= $container->get('mysql');
       $this->container = $container;
    }


    public function signupPost($request, $response, $args)
    {       // reading json params
      $data = $request->getParsedBody();
      $sql = $this->sql;
      $email = strtolower($data['email']);
      $name = $data['name'];
      $password = $data['password'];

      if (strlen($email) === 0) {
        emitError($response, 403, 'email address required');
      } //return error

      $checkData = $sql->single('usr_details', 'id, activated, password_hash', 'email=?', [$email]);
      if (!$checkData) {
        //create user and hash. Send activation email
        $passHash = new \User\Tools\PassHash();
        $passwordHash = $passHash->hash($password);
        $hash = bin2hex(random_bytes(32));
        $data['hash'] = $hash;
        
        $userId = $sql->insert('usr_details', 'name, email, password_hash, hash, profile_url', [$name, $email, $passwordHash, $hash, '']);
        $data['id'] = $userId;

        $this->sendActivationEmail($userId);
      } else {
        //may have previously loggen in with linkedIn etc so...
        if (!$checkData['password_hash']) {
          //create user and hash. Send activation email
          $passHash = new \User\Tools\PassHash();
          $passwordHash = $passHash->hash($password);
          $hash = bin2hex(random_bytes(32));
          $data['hash'] = $hash;
          $userId = $checkData['id'];
          $sql->update(
            'usr_details',
            'name=?, email=?, password_hash=?, hash=?, profile_url=?',
            'id=?',
            [$name, $email, $passwordHash, $hash, '', $userId]
          );
          $data['id'] = $userId;

          $this->sendActivationEmail($userId);

        } else {
          return emitError($response, 403, 'already exists');
        }
      }
      $loginObject = (new \User\Login($this->container))->loginReturnObject($userId);
      return emit($response, $loginObject);
    }


    private function sendActivationEmail($userId) {

      $user = $this->sql->select('usr_details', 'email, name', 'id = ? AND activated = ?', [$userId, 0])[0] ?? null;
      if (!$user) return false;

      // https://stackoverflow.com/questions/2593807/md5uniqid-makes-sense-for-random-unique-tokens
      $signupHash = bin2hex(random_bytes(32));
      $this->sql->delete('usr_signup', 'user_id=?', [$userId]);
      $this->sql->insert('usr_signup', 'user_id, hash', [$userId, $signupHash]);

      $email = new \Utilities\Postmark\Emails\User\VerifyEmail($user['email'], $user['name'], $signupHash);
    }

    public function verifyPost($request, $response, $args)
    {
      $data = $request->getParsedBody();
      $hash = $data['hash'];
      $sql = $this->sql;
      //see if hash exists and if it has expired (24 hrs)
      $hashData = $sql->select('usr_signup', 'user_id, created_at', 'hash=?', [$hash])[0] ?? null;
      if (!$hashData) return emitError($response, 400, 'already verified');

      // $ageInSecs = time() - strtotime($hashData['created_at']);
      // if ($ageInSecs > 24*60*60) return emitError($response, 400, 'expired');

      $userId = $hashData['user_id'];
      $sql->delete('usr_signup', 'user_id=?', [$userId]);
      $sql->update('usr_details', 'activated=?', 'id=?', [1, $userId]);

      // return loginObject
      $loginObject = (new \User\Login($this->container))->loginReturnObject($userId);

      $this->sql->update('usr_details', 'profile_url=?', 'id=?', [$loginObject['profileUrl'], $loginObject['userId']]);

      return emit($response, $loginObject);
    }

}

 ?>
