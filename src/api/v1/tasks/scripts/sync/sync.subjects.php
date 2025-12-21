<?php
require __DIR__ . '/../../../../vendor/autoload.php';

foreach (glob(dirname(__FILE__). '/../../../helpers/*.php') as $filename)
{
    include $filename;
}

class Sync extends \Utilities\Tasks\Task {
    public function run() {
      $sync = (new \Admin\Sync\Subjects())->syncSubjects();
    }
}

// var_dump($argv);
$jobId = $argv[1];
$job = new Sync($jobId);
