<?php
require __DIR__ . '/../../../../vendor/autoload.php';

class CovidStaff extends \Utilities\Tasks\Task {
    public function run() {
      $staff = (new \SMT\Tools\Covid\Staff())->sendHODSEmails();
    }
}

// var_dump($argv);
$jobId = $argv[1];
$job = new CovidStaff($jobId);
