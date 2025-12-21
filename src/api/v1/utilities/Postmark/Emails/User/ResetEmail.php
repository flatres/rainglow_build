<?php
namespace Utilities\Postmark\Emails\User;

class ResetEmail {

		public function __construct($to, $name, $hash)
		{
			//to, subject, tag, track
      $postmark = new \Utilities\Postmark\Client($to, "Learn Flow - Password Reset", "Learn Flow - Password Reset", true);

      $url = $_ENV['SITE_URL'] . "/reset?h=" . $hash;

      $content = $postmark->template('reset_email', array("name"=>$name, "action_url" => $url));

      $postmark->send("Learn Flow - Password Reset", $content);

		}

}

?>
