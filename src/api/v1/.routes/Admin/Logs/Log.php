<?php

/**
 * Description

 * Usage:

 */
 // https://stackoverflow.com/questions/1375501/how-do-i-throttle-my-sites-api-users
namespace Admin\Logs;
use Psr\Container\ContainerInterface as Container;

define('OS_ENV', $_SERVER["OS_ENV"]);

class Log
{
    protected $container;

    public function __construct(Container $container)
    {
      // unset($container['errorHandler']);
      // unset($container['phpErrorHandler']);
    }

    //retrives roles along with names of those with that role
    public function log_GET($request, $response, $args)
    {
      $handle = fopen(LOG_PATH, 'r');
      // https://stackoverflow.com/questions/2961618/how-to-read-only-5-last-line-of-the-text-file-in-php
        $data=array();
        $fp = fopen(LOG_PATH, "r");
        $count = 1000;
        while(!feof($fp))
        {
           $line = fgets($fp, 4096);
           array_push($data, $line);
           if (count($data)>$count)
               array_shift($data);
        }
        fclose($fp);

        $log = $this->processLog($data);

    return emit($response, $log);
  }

  public function resources_GET($request, $response, $args)
  {
    $resources = $this->getResources();
    $now = date('c');
    return emit($response, ['resources' => $resources, 'time' => $now, 'OS_ENV' => OS_ENV]);
  }

  public function connections_GET($request, $response, $args)
  {
    $connections = [];

    try {
      $sql = new \Dependency\Databases\Ada();
      $ada = true;
    } catch (\PDOException $e) {
      $ada = false;
    }

    $connections[] = [
      'label' => 'Ada',
      'value' => $ada
    ];

    try {
      $sql = new \Dependency\Databases\ISams();
      $isams = true;
    } catch (\PDOException $e) {
      $isams = false;
    }

    $connections[] = [
      'label' => 'Isams',
      'value' => $isams
    ];

    try {
      $sql = new \Dependency\Databases\MCCustom();
      $MCC = true;
    } catch (\PDOException $e) {
      $MCC = false;
    }

    $connections[] = [
      'label' => 'MCC',
      'value' => $MCC
    ];

    try {
      $sql = new \Dependency\Exgarde();
      $exgarde = true;
    } catch (\PDOException $e) {
      $exgarde = false;
    }

    $connections[] = [
      'label' => 'Exgarde',
      'value' => $exgarde
    ];

    // try {
    //   $sql = new \Dependency\ActiveDirectory();
    //   $sql->connect();
    //   $ldap = true;
    // } catch (\Exception $e) {
    //   $ldap = false;
    // }
    //
    // $connections[] = [
    //   'label' => 'LDAP',
    //   'value' => $ldap
    // ];

    return emit($response, $connections);
  }

  private function processLog($data)
  {
    $log = [];
    foreach($data as $d){
      $timestamp = $this->getStringBetween($d, '[', ']');
      $level = $this->getStringBetween($d, 'ada.', ':');
      $id = $this->getStringBetween($d, '"uid":"', '"');
      $message = $this->getStringBetween($d, 'ada.', '{"uid":"');
      $l = [
        'id'      => $id,
        'time'    => $timestamp,
        'level'   => $level,
        'message' => $message
      ];
      $log[] = $l;
    }

    $now = date('c');
    return ['messages' => $log, 'time' => $now, 'OS_ENV' => OS_ENV];
  }

  private function getStringBetween($string, $start, $end)
  {
    $string = ' ' . $string;
    $ini = strpos($string, $start);
    if ($ini == 0) return '';
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
  }
// https://stackoverflow.com/questions/15538687/using-php-to-stream-data-of-programs-such-as-htop
  private function getResources()
  {
    switch (OS_ENV) {
      case 'OSX' :
        $top = shell_exec("top -l 1 -n 1");
        $cpuIdle = round($this->getStringBetween($top, 'sys, ', '% idle'));
        $memTotal = 16;
        $memFree = 16 - round($this->getStringBetween($top, 'PhysMem: ', 'G'));
        $memInfo = 0;
        $cores = [
          [
            'id'  => 1,
            'idle'  =>50
          ],
          [
            'id'  => 2,
            'idle'  =>75
          ]
        ];
        $disk = $this->getDiskInfo("/");

        break;
      case 'UBUNTU' :
        $cpu = $this->getUbuntuCPU();
        $top = '';
        $cpuIdle = $cpu['idle'];
        $cores = $cpu['cores'];
        $memTotal = 0;
        $memFree = 0;
        $memInfo = $this->getSystemMemInfo();
        $disk = $this->getDiskInfo("/var/www");
        break;
    }

    return [
      'cpuIdle' => $cpuIdle,
      'memTotal'  => $memTotal,
      'memFree' => $memFree,
      'cores'   => $cores,
      'memory'  => $memInfo,
      'disk'    => $disk
    ];

    // return system("top -n 1");
  }
  // https://gist.github.com/rlemon/1780212
  private function getUbuntuCPU() {
    /* get core information (snapshot) */
    $stat1 = $this->GetCoreInformation();
    /* sleep on server for one second */
    sleep(1);
    /* take second snapshot */
    $stat2 = $this->GetCoreInformation();
    /* get the cpu percentage based off two snapshots */
    $cpu = $this->GetCpuPercentages($stat1, $stat2);

    $i = 0;
    $sum = 0;
    foreach($cpu as $c) {
      $sum = $sum + $c['idle'];
      $i++;
    }
    if ($i == 0) return 0;
    return [
      'idle'  => round($sum / $i, 2),
      'cores' => array_values($cpu)
    ];

  }

  /* Gets individual core information */
  private function GetCoreInformation() {
  	$data = file('/proc/stat');
  	$cores = array();
  	foreach( $data as $line ) {
  		if( preg_match('/^cpu[0-9]/', $line) )
  		{
  			$info = explode(' ', $line );
  			$cores[] = array(
  				'user' => $info[1],
  				'nice' => $info[2],
  				'sys' => $info[3],
  				'idle' => $info[4]
  			);
  		}
  	}
  	return $cores;
  }
/* compares two information snapshots and returns the cpu percentage */
private function GetCpuPercentages($stat1, $stat2) {
	if( count($stat1) !== count($stat2) ) {
		return;
	}
	$cpus = array();
	for( $i = 0, $l = count($stat1); $i < $l; $i++) {
		$dif = array();
		$dif['user'] = $stat2[$i]['user'] - $stat1[$i]['user'];
		$dif['nice'] = $stat2[$i]['nice'] - $stat1[$i]['nice'];
		$dif['sys'] = $stat2[$i]['sys'] - $stat1[$i]['sys'];
		$dif['idle'] = $stat2[$i]['idle'] - $stat1[$i]['idle'];
		$total = array_sum($dif);
		$cpu = array();
		foreach($dif as $x=>$y) {
      $cpu[$x] = $total == 0 ? 0 : round($y / $total * 100, 1);
    }
    $cpu['id'] = $i;

		$cpus['cpu' . $i] = $cpu;
	}
	return $cpus;
}

private function getSystemMemInfo()
{
    $data = explode("\n", file_get_contents("/proc/meminfo"));
    $meminfo = array();
    foreach ($data as $line) {
        $breakdown = explode(":", $line);
        if (isset($breakdown[1])) {
          $breakdown[1] = str_replace('kB', '', $breakdown[1]);
          $meminfo[$breakdown[0]] = trim($breakdown[1]);
        }
    }

    return round((100*($meminfo['MemTotal'] - $meminfo['MemFree']) / $meminfo['MemTotal']),1);
    // return $meminfo;
}

private function getDiskInfo($path) {
  /* get disk space free (in bytes) */
  $df = disk_free_space($path);
  /* and get disk space total (in bytes)  */
  $dt = disk_total_space($path);
  /* now we calculate the disk space used (in bytes) */
  $du = $dt - $df;
  /* percentage of disk used - this will be used to also set the width % of the progress bar */
  $dp = sprintf('%.2f',($du / $dt) * 100);

  /* and we formate the size from bytes to MB, GB, etc. */
  $df = $this->formatSize($df);
  $du = $this->formatSize($du);
  $dt = $this->formatSize($dt);

  return [
    'total' => intval($dt),
    'free'  => intval($df),
    'percentage'  => intval($dp)
  ];

}

private function formatSize($bytes)
{
        $types = array( 'B', 'KB', 'MB', 'GB', 'TB' );
        for( $i = 0; $bytes >= 1024 && $i < ( count( $types ) -1 ); $bytes /= 1024, $i++ );
        return( round( $bytes, 2 ) . " " . $types[$i] );
}

}
