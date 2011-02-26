<?php
error_reporting(E_ALL | E_STRICT);
$error = 0;
$start = microtime(true);

include_once __DIR__ . '/../neevo.php';

$args = getopt('d:v');
$config = parse_ini(__DIR__ . '/config.ini');

$driver = isset($args['d']) ? strtolower($args['d']) : $config['default'];

if(!isset($config[$driver]))
  error("Driver '$driver' is not available");

try{

  $db = new Neevo($config[$driver]);
  echo "Driver: $driver\n";

  foreach(glob(__DIR__ . '/*.phpt') as $test){
    ob_start();
    include_once $test;

    if(!preg_match("~^--TEST--\r?\n.*\r?\n--FILE--\r?\n(.*)--EXPECTF?--\r?\n(.*)~s", ob_get_clean(), $matches))
      error("Invalid test file " . basename($test));

    elseif($matches[1] !== $matches[2]){
      error(basename($test) . " - test failed", false);
      if(isset($args['v']))
        error("EXPECT:\n$matches[2]\n---\nRESULT:\n$matches[1]\n---");
    }
  }

} catch(Exception $e){
    error(get_class($e) . ': ' . $e->getMessage() . ($e instanceof NeevoException ? "\nSQL: " . $e->getSql() : ''));
}

printf("\n%d queries, %.3F sec, %d KB\n", $db->queries(), microtime(true) - $start, memory_get_peak_usage() / 1024);
if($error) exit(1);


/**
 * Parse INI file and expand %% variables.
 * @param string $file
 * @return array
 */
function parse_ini($file){
  $config = $c = parse_ini_file($file, true);
  array_walk_recursive($config, function(&$value, $key) use($c){
    $value = str_replace('%dbPath%', realpath(__DIR__ . $c['dbPath']), $value);
  });
  return $config;
}

/**
 * Write error on STDERR and exit with given code.
 * @param string $message
 * @param int $exit_code
 */
function error($message, $exit_code = 1){
  global $error;
  fwrite(STDERR, "Error: $message\n");
  $error++;
  if($exit_code) exit($exit_code);
}
