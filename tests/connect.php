<?php

include_once dirname(__FILE__). '/../neevo.php';

$db = new Neevo('mysql', false);

$db->connect(array(
  'host' => 'localhost',
  'database' => 'neevo',
  'username' => 'root',
  'encoding' => 'utf8'
));

$db->setErrorReporting(Neevo::E_STRICT);

/** @return Neevo */
function db(){
  global $db;
  return $db;
}
?>
