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
$sql->prefix('dp_');

$q = new NeevoMySQLQuery($sql->options(),'select', 'table_name');

$q->columns(array('column', '55+3 as xyz', 'SHA1(col)', 'SHA1(col) as col_hash','*'))->where('email', 'smasty@yahoo.com')->order('name ASC', 'email DESC')->limit(5, 6);

//print_r($sql);
print_r($q);
var_dump($q);

$q->dump();


?>