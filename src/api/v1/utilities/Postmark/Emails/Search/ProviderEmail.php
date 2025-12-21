<?php
namespace Utilities\Postmark\Emails\Search;

class ProviderEmail {

		public function __construct($providerEmail, $userEmail, $subject, $body)
		{
			//to, subject, tag, track
      $postmark = new \Utilities\Postmark\Client($providerEmail, $subject, "Provider Enquiry", true, $userEmail);

      $content = $postmark->template('provider_enquiry', array("body"=>$body));

      $postmark->send($subject, $content);

		}

}

?>
