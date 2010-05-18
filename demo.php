<?php

include('neevo.php');

$sql = new Neevo(array(
  'host'     => 'localhost',
  'username' => 'root',
  'password' => '',
  'database' => 'layer_test',
  'encoding' => 'utf8'
));

$sql->error_reporting(1);
$sql->prefix('dp_');

?>