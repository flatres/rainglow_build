<?php
require __DIR__ . '/../../../../vendor/autoload.php';

class CovidStudents extends \Utilities\Tasks\Task {
    public function run() {
      $students = (new \SMT\Tools\Covid\Students())->sendTodayEmails();
    }
}

// var_dump($argv);
$jobId = $argv[1];
$job = new CovidStudents($jobId);
