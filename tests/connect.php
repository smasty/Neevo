<?php

include_once dirname(__FILE__). '/../neevo.php';

// MySQL
$mysql = new Neevo('MySQL', false);
$mysql->connect(array(
  'host' => 'localhost',
  'database' => 'neevo',
  'username' => 'root',
  'encoding' => 'utf8'
));
$mysql->setErrorReporting(Neevo::E_STRICT);

// SQLite
$sqlite = new Neevo('SQLite', false);
$sqlite->connect(array('database' => '../neevo.sqlite'));

/** @return Neevo */
function db(){
  global $mysql;
  return $mysql;
}

function driver(){
  return strtolower(substr(get_class(db()->driver()), 11));
}
?>
