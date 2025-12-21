<?php
require __DIR__ . '/../../../../vendor/autoload.php';

class SyncStudents extends \Utilities\Tasks\Task {
    public function run() {
      $students = (new \Admin\Sync\Students())->syncAllStudents();
    }
}

// var_dump($argv);
$jobId = $argv[1];
$job = new SyncStudents($jobId);
