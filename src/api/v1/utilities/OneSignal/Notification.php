<?php
declare(strict_types=1);

namespace Utilities\OneSignal;

use \OneSignal\Config;
use \OneSignal\OneSignal;
use \Symfony\Component\HttpClient\Psr18Client;
use \Nyholm\Psr7\Factory\Psr17Factory;

define('ONESIGNAL_APP_ID', $_ENV["ONESIGNAL_APP_ID"]);
define('ONESIGNAL_APP_KEY', $_ENV["ONESIGNAL_APP_KEY"]);

class Notification {
  private $oneSignal;
  private $sql;

	public function __construct(){

    $this->sql = new \Dependency\Databases\LearnFlow();
		try {

      $config = new Config(ONESIGNAL_APP_ID, ONESIGNAL_APP_KEY);
      $httpClient = new Psr18Client();
      $requestFactory = $streamFactory = new Psr17Factory();

      $this->oneSignal = new OneSignal($config, $httpClient, $requestFactory, $streamFactory);

    }catch(Exception $e){}
  }

  public function send(string $message, int $travellerId = null){
    return 
    $trip = $this->trip;
    $userId = $trip->leaderId;
    $title = $trip->name;

    $this-> sql->insert(
      'usr_notifications',
      'user_id, traveller_id, trip_id, message',
      [$userId, $travellerId, $trip->id, $message]
    );

    $settings = array(
        'contents' => [
            'en' => $message
        ],
				'headings' => [
            'en' => ucwords($title)
        ],
        'include_external_user_ids' => [$userId],
        // 'included_segments' => ['All'],
        'data' => ['update' => true],
        // 'filters' => [
        //     [
        //         'field' => 'tag',
        //         'key' => 'id',
        //         'relation' => '=',
        //         'value' => $userID,
        //     ],
        // ]
      );

    $this->publish($settings);

  }

  public function sendBasic(int $userId, string $message, string $title){

    $settings = array(
        'contents' => [
            'en' => $message
        ],
				'headings' => [
            'en' => ucwords($title)
        ],
        'include_external_user_ids' => [$userId],
        // 'included_segments' => ['All'],
        'data' => ['update' => true],
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
