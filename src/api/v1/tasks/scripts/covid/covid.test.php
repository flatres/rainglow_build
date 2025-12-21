<?php
require __DIR__ . '/../../../../vendor/autoload.php';

class CovidTest extends \Utilities\Tasks\Task {
    public function run() {
      $staff = (new \SMT\Tools\Covid\Test())->sendEmail();
    }
}

// var_dump($argv);
$jobId = $argv[1];
$job = new CovidTest($jobId);
