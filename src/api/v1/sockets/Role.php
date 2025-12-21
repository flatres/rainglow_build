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

class Role
{
    private $socketId = '';
    protected $container;

    public function __construct($roleId)
    {
       $this->roleId = $roleId; //unique to this updater. Usually best as the route
    }

    public function publish (array $data)
    {
      $this->send($data);
    }


    private function send (array $data = array())
    {

      $entryData = array(
                           'data'     =>  $data,
                           'when'     => time(),
                           'roleId'   => $this->roleId,
                           'socketId' => 'role'
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
