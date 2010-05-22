<pre><?php

include('neevo.php');

$sql = new Neevo(array(
  'host'     => 'localhost',
  'username' => 'root',
  'password' => '',
  'database' => 'layer_test',
  'encoding' => 'utf8'
));

$sql->errors(1);

$q = new NeevoMySQLQuery($sql->options(),'select', 'table_name');

$q->where('SHA1(email) LIKE', '%@gmail.com', 'or')->where('email LIKE', '%@yahoo.com');

print_r($sql);
print_r($q);


?>