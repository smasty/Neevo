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
  'name'  => 'Smasty',
  'mail' => 'xmartin@smasty.net',
  'city' => 'Hlohovec',
  'friends'  => 23
);

//$insert = $sql->insert('client', $data)->run();

//$update = $sql->update('client', $data)->where('name LIKE', '%masty')->run();


$delete = $sql->delete('client')->where('id >', 228)->run();

echo $delete->affected();

//print_r($sql);
print_r($q);


?></pre>