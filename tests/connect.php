<?php

include_once dirname(__FILE__). '/../neevo.php';

$db_dir = realpath(dirname(__FILE__) . '/../../databases');

if($driver == 'mysql'){
  $db = new Neevo(array(
    'driver' => 'MySQL',
    'database' => 'neevo',
    'username' => 'root',
    'charset' => 'utf8'
  ));
}

elseif($driver == 'mysqli'){
  $db = new Neevo(array(
    'driver' => 'MySQLi',
    'database' => 'neevo',
    'username' => 'root',
    'charset' => 'utf8'
  ));
}

elseif($driver == 'sqlite'){
  $db = new Neevo(array(
    'driver' => 'SQLite',
    'file' => $db_dir . '/sqlite.db',
    'charset' => 'UTF-8',
    'dbcharset' => 'UTF-8'
  ));
}

elseif($driver == 'sqlite3'){
  $db = new Neevo(array(
    'driver' => 'SQLite3',
    'file' => $db_dir . '/sqlite3.db',
    'charset' => 'UTF-8',
    'dbcharset' => 'UTF-8'
  ));
}

elseif($driver == 'pgsql'){
  $db = new Neevo(array(
    'driver' => 'pgsql',
    'user' => 'root',
    'dbname' => 'neevo',
    'charset' => 'utf8'
  ));
}

else{
  fwrite(STDERR, "Driver '$driver' is not available.\n");
  exit(1);
}
