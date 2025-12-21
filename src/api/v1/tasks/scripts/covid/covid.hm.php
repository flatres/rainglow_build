<?php
require __DIR__ . '/../../../../vendor/autoload.php';

class CovidHM extends \Utilities\Tasks\Task {
    public function run() {
      $staff = (new \SMT\Tools\Covid\Students())->sendHMEmails();
    }
}

// var_dump($argv);
$jobId = $argv[1];
$job = new CovidHM($jobId);
