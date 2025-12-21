<?php
namespace Utilities\Postmark\Emails\User;

class VerifyEmail {

		public function __construct($to, $name, $hash)
		{
			//to, subject, tag, track
      $postmark = new \Utilities\Postmark\Client($to, "Thanks for logging into Learn Flow!", "Verify Email", true);

      $url = $_ENV['SITE_URL']."/verified?user=" . $hash;

      $content = $postmark->template('verify_email', array("name"=>$name, "action_url" => $url));

      $postmark->send("Verify Email Address", $content);

		}

}

?>
