<?php

include_once dirname(__FILE__). '/../neevo.php';

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
    'file' => 'tests/neevo.sqlite',
    'charset' => 'UTF-8',
    'dbcharset' => 'UTF-8'
  ));
}

elseif($driver == 'sqlite3'){
  $db = new Neevo(array(
    'driver' => 'SQLite3',
    'file' => 'tests/neevo.sqlite3',
    'charset' => 'UTF-8',
    'dbcharset' => 'UTF-8'
  ));
}

else{
  fwrite(STDERR, "Driver '$driver' is not available.\n");
  exit(1);
}