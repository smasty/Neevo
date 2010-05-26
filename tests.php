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

$data = array(
  'name'  => 'Amon Smasty',
  'mail' => 'smasty@example.com',
  'city' => 'New York',
  'about' => 'Lorem ipsum dolorem...',
  'friends'  => 23
);

$select = $sql->select('*', 'client')->limit(5)->dump();

echo "rows: ".$sql->rows($select->run());


//print_r($sql);
print_r($q);


?></pre>