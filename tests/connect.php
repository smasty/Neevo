<?php

include_once dirname(__FILE__). '/../neevo.php';

// Cache
$cache = new NeevoCacheSession;

// MySQL
$mysql = new Neevo('MySQL', $cache);
$mysql->connect(array(
  'host' => 'localhost',
  'database' => 'neevo',
  'username' => 'root',
  'charset' => 'utf8'
));
$mysql->setErrorReporting(Neevo::E_STRICT);


// SQLite
$sqlite = new Neevo('SQLite', $cache);
$sqlite->connect(array('file' => 'tests/neevo.sqlite'));
$sqlite->setErrorReporting(Neevo::E_STRICT);


// SQLite 3
$sqlite3 = new Neevo('SQLite3', $cache);
$sqlite3->connect(array('file' => 'tests/neevo-sqlite3.sqlite'));
$sqlite3->setErrorReporting(Neevo::E_STRICT);


// MySQLi
$mysqli = new Neevo('MySQLi', $cache);
$mysqli->connect(array(
  'host' => 'localhost',
  'database' => 'neevo',
  'username' => 'root',
  'charset' => 'utf8'
));
$mysqli->setErrorReporting(Neevo::E_STRICT);



/** @return Neevo */
function db(){
  global $driver;
  global $mysql, $sqlite, $mysqli, $sqlite3;
  
  return $$driver;
}

function driver(){
  return strtolower(substr(get_class(db()->driver()), 11));
}
?>
