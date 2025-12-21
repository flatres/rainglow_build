<?php
namespace User;
use Psr\Container\ContainerInterface as Container;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
/**
 * Manages all aspects of user login. It checks the password, creates a new session and return the authorisatuion key used
 * in subsequent api calls. It also generates the return object which included the user id and permissions object
 */

class Login
{
    /** @var \Slim\Container
     */
    const LOCKOUT_TIME_MINUTES = 2;
    protected $container;
    private $isNewUser = false;
    private $oneSignal, $sql;

    public function __construct(Container $container)
    {
       $this->sql= $container->get('mysql');
       $this->oneSignal = new \Utilities\OneSignal\Notification();
    }

    public function googleLogin($request, $response, $args)
    {
      $data = $request->getParsedBody();
      $token = $data['credential'];
      $decode = (array)json_decode(base64_decode(str_replace('_', '/', str_replace('-','+',explode('.', $token)[1]))));
      $email = strtolower($decode['email']);
      $firstName = $decode['given_name'] ?? '';
      $lastName = $decode['family_name'] ?? '';
      $name = $decode['name'];
      $locale = $decode['locale'] ?? null;

      //check if user already exists
      $exists = $this->sql->single('usr_details', 'id', 'email=?', [$email]);
      if (!$exists) {
        $id = $this->sql->insert(
          'usr_details',
          'email, name, locale, profile_url', [$email, $name, $locale, '']
        );

      } else {
        $id = $exists['id'];
      }
      $this->writeLog($request, $email, false);
      $loginObject = $this->loginReturnObject($id);
      return emit($response, $loginObject);

    }

     public function linkedinLogin($request, $response, $args)
    {
      $data = $request->getParsedBody();
      $code = $data['code'];
      $redirect = $data['redirect'];

      $clientId = $_ENV["L_CLIENT_ID"];
      $secret = $_ENV["L_CLIENT_SECRET"];

      $client = new \GuzzleHttp\Client();

      $params = [
              'grant_type'    => 'authorization_code',
              'code'          => $code,
              'client_id'     => $clientId,
              'client_secret' => $secret,
              'redirect_uri'  => $redirect
      ];

      $guzzle = $client->request('POST', 'https://www.linkedin.com/oauth/v2/accessToken', [
          'headers' => [
            'Content-Type' => 'application/x-www-form-urlencoded'
          ],
          'form_params' => $params
        ]);
      $tokenData = json_decode((string)$guzzle->getBody());

      $accessToken = 'Bearer ' . $tokenData->access_token;

      $guzzle = $client->request('GET', 'https://api.linkedin.com/v2/emailAddress?q=members&projection=(elements*(handle~))', [
          'headers' => [
            'Authorization' => $accessToken
          ]
        ]);

      // https://stackoverflow.com/questions/50250453/how-to-get-email-address-using-linkedin-v2-api
      $emailData = (array)json_decode((string)$guzzle->getBody());
      $handles = (array)$emailData['elements'][0];
      $email = strtolower($handles['handle~']->emailAddress);

      $guzzle = $client->request('GET', 'https://api.linkedin.com/v2/me', [
        'headers' => [
          'Authorization' => $accessToken
        ]
      ]);
      $userData = (array)json_decode((string)$guzzle->getBody());

      $firstName = $userData['localizedFirstName'];
      $lastName = $userData['localizedLastName'];
      $name = $firstName . ' ' . $lastName;
      $country = $userData['firstName']->preferredLocale->country;
      $lang = $userData['firstName']->preferredLocale->language;
      $locale = $lang . '-' . $country;
      //check if user already exists
      $exists = $this->sql->single('usr_details', 'id', 'email=?', [$email]);
      if (!$exists) {
        $id = $this->sql->insert(
          'usr_details',
          'email, name, locale, profile_url', [$email, $name, $locale, '']
        );

      } else {
        $id = $exists['id'];
      }

      $this->writeLog($request, $email, false);
      $loginObject = $this->loginReturnObject($id);
      return emit($response, $loginObject);

    }


    public function login($request, $response, $args)
    {
       // reading json params
      $data = $request->getParsedBody();
      // var_dump($request); exit();
      // return emitError($response, 400, "");
      if(!isset($data['login']) || !isset($data['password'])){
        return emitError($response, 400, "Unrecognised email address / password");
      }
      // reading post params
      $login = $data['login']; $password = $data['password'];

      $id = $this->checkLoginNative($login, $password);

      if ($id !== FALSE) {
          $this->writeLog($request, $login, false);
          $loginObject = $this->loginReturnObject($id);
          // $this->oneSignal->sendBasic($id, 'Welcome back ' . $loginObject['name'], 'Trip-IN');
          return emit($response, $loginObject);
      } else {
          $user = $this->sql->single('usr_details', 'failed_attempts', 'email=? AND disabled = 0', [$login]);
          if ($user) {
            $failedAttempts = $user['failed_attempts'];

            $failedAttempts++;
            $locked = $failedAttempts > 3 ? 1 : 0;

            $this->sql->update('usr_details', 'failed_attempts=?, is_locked=?', 'email=? AND disabled = 0', [$failedAttempts, $locked, $login]);
            if ($locked == 1) {
              //write log
              $ip = $request->getServerParams()['REMOTE_ADDR'];
              $userId = $this->sql->select('usr_details', 'id', 'email=? AND disabled = 0', array($login))[0]['id'];
              // $this->log->warning("User Account Locked usr:$login ip:$ip");
              $time = self::LOCKOUT_TIME_MINUTES;
              $data = [
                'success' => false,
                'message' => "Too many attempts. Account locked for $time minutes."
              ];
              return emitError($response, 400, $data);
            } else {
            }
          }
          $data = [
                'success' => false,
                'message' => "Unrecognised email address / password"
              ];
          return emitError($response, 400, $data);
      }
    }

    public function profilePut($request, $response, $args) {
      $profile = (object)$request->getParsedBody();
      global $userId;
      $this->sql->update('usr_details', 'name=?, about=?', 'id=?', [$profile->name, $profile->about, $userId]);
      return emit($response, $profile);

    }

    // https://stackoverflow.com/questions/11511511/how-to-save-a-png-image-server-side-from-a-base64-data-string
    public function profilePicPost($request, $response, $args) {
      global $userId;
      $image = $request->getParsedBody()['data'];

      if(strlen($image) == 0) {
        // $url = FILESTORE_URL . '/profile_pics/default.png';
        $url = '';
      } else {
        $user = $this->sql->select('usr_details', 'hash', 'id=?', [$userId]);
        $time = time();
        $hash = $user[0]['hash'];
        $fileName = "profile_{$userId}_{$hash}_{$time}.png";
        $path = FILESTORE_PATH . 'profile_pics/';
        $file = $path . $fileName;

        $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $image));

        foreach (glob($path . "*" . $hash . "*.png") as $filename) {
          unlink($filename);
        }

        file_put_contents($file, $data);

        $url = FILESTORE_URL . '/profile_pics/' . $fileName;
      }

      $this->sql->update('usr_details', 'profile_url=?', 'id=?', [$url, $userId]);

      return emit($response, ['profileUrl' => $url]);
    }

    private function writeLog($request, string $login, bool $hasFailed)
    {
      $ip = $request->getServerParams()['REMOTE_ADDR'];
      $hasFailed = $hasFailed ? 1 : 0;
      $user_id = $this->sql->select('usr_details', 'id', 'email=?', [$login])[0]['id'];
      $this->sql->insert('usr_log', 'user_id, ip, failed', [$user_id, $ip, $hasFailed]);
    }

    private function checkLoginNative($login, $password)
    {
      $sql = $this->sql;
      $data = [$login];

      $d = $sql->select('usr_details', 'id, password_hash', 'email = ? AND activated = 1 AND is_locked = 0 ', $data, TRUE);
      // var_dump($d);
      if ($d) {
          $id = $d[0]['id'];
          if (Tools\PassHash::check_password($d[0]['password_hash'], $password)) {
             $sql->update('usr_details', 'last_login=NOW()', 'id=?', array($id));
             return $id;
          } else {
              return false;
          }

      } else {
          return false;
      }
    }

    public function loginReturnObject($id)
    {
      global $userId;
      $userId = $id;

      $sql = $this->sql;

      $data = $sql->select('usr_details', 'name, email, about, profile_url, isAdmin, hash', 'id=?', array($id));

      if(!$data) return null;

      $d = $data[0];

      $returnObj = array();
      $returnObj['userId'] = $id;
      $returnObj['name'] = $d['name'];
      $returnObj['email'] = $d['email'];
      $returnObj['isAdmin'] = (bool)$d['isAdmin'];
      $returnObj['about'] = $d['about'];
      $returnObj['isProvider'] = $this->sql->single('providers', 'id', 'userId=?', [$id]) ? true : false;
      $returnObj['auth'] = $this->newSession($id);
      $returnObj['oneSignal'] = $_ENV["ONESIGNAL_APP_ID"];
      $returnObj['profileUrl'] = $d['profile_url'];
      // $returnObj['profileUrl'] = $d['profile_url'] ? $d['profile_url'] : FILESTORE_URL . '/profile_pics/default.png';

      return $returnObj;

    }

    private function generateApiKey()
    {
         return md5(uniqid(rand(), true));
    }

    private function generateToken() {
         return md5(uniqid(rand(), true));
    }

    private function newSession($id){
      $sql = $this->sql;

      //delete any old sessions
      // $sql->delete('usr_sessions', 'user_id=?', array($id));

      //generate a new token to be stored by the brower and ge the current time
      $auth = $this->generateToken();
      $start = time();

      //write the new session to the db
      $data = array($id, $auth, $start);
      $sql->insert('usr_sessions', 'user_id, token, started', $data);

      return $auth;

   }

}

 ?>
