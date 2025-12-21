<?php

/**
 * Description

 * Usage:

 */
namespace Sockets;

use \ZMQContext;
use \ZMQ;

class Console
{
    protected $container;
    public $lineIndex = 0;

    public function __construct($auth = null, $consoleId = '')
    {
       $this->auth = $auth;
       $this->consoleId = $consoleId;

    }

    public function publish (string $lineText, int $indent = 0)
    {
      $this->send($lineText, $indent);
    }

    public function error (string $lineText)
    {
      $this->send($lineText, 0, true);
    }

    public function replace (string $lineText)
    {
      $this->send($lineText, 0, false, true);
    }

    private function send (string $lineText, int $indent = 0, bool $isError = false, bool $replacePrevious = false)
    {
      if (!$this->auth) return true;
      
      $entryData = array(
                           'message'    => "$lineText",
                           'when'       => time(),
                           'lineIndex'  => $this->lineIndex++,
                           'indent'     => $indent,
                           'isError'    => $isError,
                           'replace'    => $replacePrevious,
                           'socketId' => 'console',
                           'auth'       => $this->auth //auth is required so that it is send to the correct Socket
                        );

       // This is our new stuff
       $context = new \ZMQContext();
       // $context = new \React\ZMQ\Context();
       try{
         $socket = $context->getSocket(\ZMQ::SOCKET_PUSH, 'console'); //,'console'
         $socket->connect(ZMQ_SERVER);

       }catch(\ZMQSocketException $e){

         echo "An error occured\n";
         echo "{$e->getMessage()}\n";

       }

      // echo $entryData['message'];
       $socket->send(json_encode($entryData));

    }

}
