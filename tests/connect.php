<?php

include_once dirname(__FILE__). '/../neevo.php';

$db = new Neevo($driver, new NeevoCacheSession);

if($driver == 'mysql'){
  $db->connect(array(
    'database' => 'neevo',
    'username' => 'root',
    'charset' => 'utf8'
  ));
}

elseif($driver == 'mysqli'){
  $db->connect(array(
    'database' => 'neevo',
    'username' => 'root',
    'charset' => 'utf8'
  ));
}

elseif($driver == 'sqlite'){
  $db->connect(array('file' => 'tests/neevo.sqlite'));
}

elseif($driver == 'sqlite3'){
  $db->connect(array('file' => 'tests/neevo-sqlite3.sqlite'));
}

else{
  trigger_error('Driver does not exist', E_USER_ERROR);
}
