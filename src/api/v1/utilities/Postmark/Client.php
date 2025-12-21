<?php

namespace Utilities\Postmark;

use \Postmark\PostmarkClient;
use \Postmark\Models\PostmarkException;
use \Postmark\Models\PostmarkAttachment;

define('POSTMARK_KEY', $_ENV["POSTMARK_KEY"]);


class Client {

	private $to, $cc;
	private $subject;
	private $htmlbody, $textbody, $tag, $track;

	public function __construct($to, $subject, $tag, $track = true, $cc = null) {
		$this->to = $to;
		$this->subject = $subject;
    $this->tag = $tag;
    $this->track = $track;
    $this->cc = $cc;
	}

  public function send($title, $htmlcontent, $textcontent = ""){

		  $htmlbody = $this->template('skeleton', array('title' => $title, 'content'=>$htmlcontent, 'year' => date("Y")));
			$textbody = $textcontent;

      $attachment = PostmarkAttachment::fromFile(dirname(__FILE__). '/img/logo.png', 'logo', 'image/png');

			$attachments = array();

			$attachments[] = array(	"Name"=> "logo.png",
														 	"Content"=> $this->encodeImg('logo.png'),
															"ContentType"=> "image/png",
															"ContentID"=> "cid:logo");

      if(isset($this->pdf)){

				$pdf = $this->pdf;

				$attachments[] = array(	"Name"=> $pdf['name'],
															 	"Content"=> base64_encode(file_get_contents($pdf['path'])),
																"ContentType"=> "application/pdf",
															 );
			}

      $message = [
        'To' => $this->to,
        'From' => "hello@learnflowmarket.com",
        'Cc' => $this->cc,
				// 'Bcc' => 'flatres@gmail.com',
        'TrackOpens' => $this->track,
        'Subject' => $this->subject,
        'TextBody' => $textbody,
        'HtmlBody' => $htmlbody,
        'Tag' => $this->tag,
        'Headers' => [ "X-CUSTOM-HEADER" => "Header content"],
        "Attachments"=> $attachments
      ];
			$client = new PostmarkClient($_ENV['POSTMARK_KEY']);

      $sendResult = $client->sendEmailBatch([$message]);

      // add to log
      $sql = new \Dependency\Databases\Learnflow();
      $sql->insert(
        'analytics_emails',
        'toEmail, subject, tag',
        [$this->to, $this->subject, $this->tag]
      );

			return $sendResult;


    }


    //variable = key / value array of replacements to make
    public function template($file, $variables){

        $path = dirname(__FILE__)."/templates/$file.html";

        $myfile = fopen($path, "r") or die();
        $data = fread($myfile,filesize($path));
        fclose($myfile);

        foreach($variables as $key => $value)
        {
          $data = str_replace("{{".$key . "}}",$value,$data);
        }

        return $data;

    }


		//returns a base64 encode of the image
		private function encodeImg($file){
      $path = dirname(__FILE__) . "/img/$file";
			$data = base64_encode(file_get_contents($path));
			return $data;
		}

}
