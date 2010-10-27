<?php

include_once dirname(__FILE__). '/../neevo.php';

// MySQL
$mysql = new Neevo('MySQL', false);
$mysql->connect(array(
  'host' => 'localhost',
  'database' => 'neevo',
  'username' => 'root',
  'charset' => 'utf8'
));
$mysql->setErrorReporting(Neevo::E_STRICT);


// SQLite
$sqlite = new Neevo('SQLite', false);
$sqlite->connect(array('file' => 'tests/neevo.sqlite'));
$sqlite->setErrorReporting(Neevo::E_STRICT);


// MySQLi
$mysqli = new Neevo('MySQLi', false);
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
  global $mysql, $sqlite, $mysqli;
  
  return $$driver;
}

function driver(){
  return strtolower(substr(get_class(db()->driver()), 11));
}
?>
