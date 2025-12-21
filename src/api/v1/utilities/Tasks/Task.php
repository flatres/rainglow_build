<?php

namespace Utilities\Tasks;

class Task
{
    private $jobId, $ada;

    public function __construct($jobId)
    {
      date_default_timezone_set('Europe/London');

      $dotenv = \Dotenv\Dotenv::createImmutable(dirname(__FILE__). '/../../');
      $dotenv->load();

      $timestamp = date("Y-m-d H:i:s", time());
      $this->jobId = (int)$jobId;

      $this->ada = new \Dependency\Databases\Ada();
      $this->ada->update('auto_jobs', 'last_run=?', 'id=?', [$timestamp, $this->jobId]);

      $this->run();
    }
}
