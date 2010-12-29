<?php

include_once dirname(__FILE__). '/../neevo.php';

if($driver == 'mysql'){
  $db = new Neevo('MySQL');
  $db->connect(array(
    'database' => 'neevo',
    'username' => 'root',
    'charset' => 'utf8'
  ));
}

elseif($driver == 'mysqli'){
  $db = new Neevo('MySQLi');
  $db->connect(array(
    'database' => 'neevo',
    'username' => 'root',
    'charset' => 'utf8'
  ));
}

elseif($driver == 'sqlite'){
  $db = new Neevo('SQLite');
  $db->connect(array('file' => 'tests/neevo.sqlite'));
}

elseif($driver == 'sqlite3'){
  $db = new Neevo('SQLite3');
  $db->connect(array('file' => 'tests/neevo.sqlite3'));
}
else{
  fwrite(STDERR, "Driver '$driver' is not available.\n");
  exit(1);
}