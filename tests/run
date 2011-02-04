<?php
error_reporting(E_ALL | E_STRICT);
$start = microtime(true);

$opts = getopt('d:bq');

$driver = isset($opts['d'])
  ? strtolower($opts['d']) : 'mysql';

include_once dirname(__FILE__) . '/connect.php';

if(!isset($opts['q'])){
  echo "Driver: $driver\n";
}

$err = 0;
foreach(glob(dirname(__FILE__).'/*.phpt') as $test){

  $file = basename($test);
  ob_start();

  try{
    include_once $test;
  } catch(NeevoException $e){
      fwrite(STDERR, get_class($e) . ": {$e->getMessage()}\nTest: $file\nSQL: {$e->getSql()}\n");
      exit(1);
  }

  if(!preg_match("~^--TEST--\r?\n(.*)\n--FILE--\r?\n(.*)--EXPECTF?--\r?\n(.*)~s", ob_get_clean(), $matches)){
    fwrite(STDERR, "Error: invalid test file $file\n");
  }
  elseif($matches[2] !== $matches[3]){
    fwrite(STDERR, "Error: Test $file failed - $matches[1]\n");
    $err++;

    if(isset($opts['b'])){
      echo "EXPECT:\n$matches[3]\n-\nRESULT:\n$matches[2]\n-\n\n";
      exit(1);
    }
  }

}

if(!$err && !isset($opts['q'])){
  echo "\nTests passed successfully.";
  printf("\n%d queries, %.3F sec, %d KB\n", $db->queries(), microtime(true) - $start, memory_get_peak_usage() / 1024);
}
if($err) exit(1);
