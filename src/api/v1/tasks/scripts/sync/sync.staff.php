<?php
require __DIR__ . '/../../../../vendor/autoload.php';

class SyncStaff extends \Utilities\Tasks\Task {
    public function run() {
      $staff = (new \Admin\Sync\Staff())->syncAllStaff();
    }
}

// var_dump($argv);
$jobId = $argv[1];
$job = new SyncStaff($jobId);
