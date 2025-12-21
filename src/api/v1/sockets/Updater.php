<?php

/**
 * Description

 *provides a means to monitor the progress of a job through sending updated array items back to the client.
 *requires a name and an key field which should prevent any issues with overwriting .

 * Usage:*
 * name should typically be the name of the route being used, $key is the primary key of the data typically id
 * progress should be between 0 and 100
 */
namespace Sockets;

use \ZMQContext;
use \ZMQ;

class Updater
{
    private $socketId = 'updater';
    protected $container;
    public $lineIndex = 0;

    public function __construct($updaterId, $key, $auth)
    {

       $this->auth = $auth;
       $this->key = $key; //eg id or firstname
       $this->updaterId = $updaterId; //unique to this updater. Usually best as the route

    }

    public function publish (array $data, int $progress = 0)
    {
      $this->send($data, $progress);
    }

    public function error (array $data = array(), int $progress = 0)
    {
      $this->send($this->key, $data, true);
    }

    private function send (array $data = array(), int $progress = 0, bool $isError = false)
    {

      $entryData = array(
                           'data'     =>  $data,
                           'when'     => time(),
                           'key'      => $this->key,
                           'isError'  => $isError,
                           'progress' => $progress,
                           'updaterId'=> $this->updaterId,
                           'socketId' => $this->socketId,
                           'auth'     => $this->auth //auth is required so that it is send to the correct Socket
                        );

       // This is our new stuff
       $context = new \ZMQContext();
       // $context = new \React\ZMQ\Context();
       // http://socketo.me/docs/push
       try{
         $socket = $context->getSocket(\ZMQ::SOCKET_PUSH);
         $socket->connect(ZMQ_SERVER);

       }catch(\ZMQSocketException $e){

         echo "An error occured\n";
        echo "{$e->getMessage()}\n";

       }
       $socket->send(json_encode($entryData));

    }

}
