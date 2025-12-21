<?php
require __DIR__ . '/../../../../vendor/autoload.php';

class Sync extends \Utilities\Tasks\Task {
    public function run() {
      $students = (new \Admin\Sync\SEN())->syncSEN();
    }
}

// var_dump($argv);
$jobId = $argv[1];
$job = new Sync($jobId);
