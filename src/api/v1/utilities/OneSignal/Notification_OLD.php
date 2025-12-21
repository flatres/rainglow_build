<?php
declare(strict_types=1);

namespace Utilities\OneSignal;

use \OneSignal\Config;
use \OneSignal\OneSignal;
use \Symfony\Component\HttpClient\Psr18Client;
use \Nyholm\Psr7\Factory\Psr17Factory;

define('ONESIGNAL_APP_ID', $_ENV["ONESIGNAL_APP_ID"]);
define('ONESIGNAL_APP_KEY', $_ENV["ONESIGNAL_APP_KEY"]);
define('ONESIGNAL_KEY', $_ENV["ONESIGNAL_KEY"]);

class Notification {
  private $oneSignal;

	public function __construct(){

		try {

      $config = new Config(ONESIGNAL_APP_ID, ONESIGNAL_APP_KEY, ONESIGNAL_KEY);
      $httpClient = new Psr18Client();
      $requestFactory = $streamFactory = new Psr17Factory();

      $this->oneSignal = new OneSignal($config, $httpClient, $requestFactory, $streamFactory);

    }catch(Exception $e){}
  }

  public function sendSingle($userID, $title, $message){

    $settings = array(

        'contents' => [
            'en' => $message
        ],
				'headings' => [
            'en' => ucwords($title)
        ],
        'included_segments' => ['All'],
        'data' => ['update' => true],
        'filters' => [
            [
                'field' => 'tag',
                'key' => 'id',
                'relation' => '=',
                'value' => $userID,
            ],
        ]
      );

    $this->publish($settings);

  }

  public function publish($settingsArray){

		try{

	    try {
	      $this->oneSignal->notifications()->add($settingsArray);

	    } catch (OneSignalException $e) {

	        $httpStatusCode = $e->getStatusCode();
	        $errors = $e->getErrors();
	        // echo $errors;
	    } catch (RequestException $e) {

	        $message = $e->getMessage();
	        // echo $message;
	    }

		}catch(Exception $e){}

  }


}
