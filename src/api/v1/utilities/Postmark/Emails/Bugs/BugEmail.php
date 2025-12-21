<?php
namespace Utilities\Postmark\Emails\Bugs;

class BugEmail {

		public function __construct($to, $from, $subject, $path, $message)
		{
			//to, subject, tag, track
      $postmark = new \Utilities\Postmark\Client($to, $subject, "Bug Report", true, $from);

      $content = $postmark->template('Auth.BugReport', array("path"=>$path, "message"=>$message));

      $postmark->send($subject, $content);

		}

}

?>
