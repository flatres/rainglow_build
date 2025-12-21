<?php
require __DIR__ . '/../../../../vendor/autoload.php';

class SubClass extends \Utilities\Tasks\Task {
    public function run() {
      echo "yeah!";
    }
}

// var_dump($argv);
$jobId = $argv[1];
// echo $jobId;
$job = new SubClass($jobId);
