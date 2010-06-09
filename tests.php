<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <title>Neevo MySQL layer DEMO</title>
    <style>
      table, td, th{border:1px solid #555;border-collapse:collapse}
      td, th{padding:2px 10px;text-align:left}
      th{background:#ddd}
    </style>
  </head>

  <body>

    <h1>Neevo MySQL layer Demo</h1>

<pre><?php

include('neevo.php');

// Connect to MySQL database
$sql = new Neevo(array(
  'host'     => 'localhost',
  'username' => 'root',
  'password' => '',
  'database' => 'neevo_demo',
  'encoding' => 'utf8'
));

// Turn Neevo error reporting ON (0 for OFF, default: ON)
$sql->errors(1);

// Set table pefix to "dp_"
$sql->prefix('dp_');



// Data for Insert/update query demos
$data = array(
  'name'  => 'John Doe',
  'mail' => 'john.doe@example.com',
  'city' => 'Springfield',
  'about' => 'Lorem ipsum dolorem...',
  'friends'  => 23
);



// INSERT QUERY
$insert = $sql->insert('client', $data);
$insert->dump();

echo "\n";

// UPDATE QUERY
$update = $sql->update('client', $data)->where('name', 'John Doe', 'OR')->where('name LIKE', '%be%')->order('id DESC', 'name', 'mail ASC')->limit(5, 2);
$update->dump();

echo "\n";

// DELETE QUERY
$delete = $sql->delete('client')->where('name', 'John Doe')->limit(1);
$delete->dump();

echo "\n";

// SELECT QUERY
$select = $sql->select('id, name, mail, city', 'client')->where('name !=', 'Fuller Strickland', 'OR')->where('name', 'John Doe')->order('id DESC', 'mail ASC', 'name')->limit(10);
$select->dump();

?></pre>

<h2>SELECT query results</h2>
<table border=0><tr><th>ID <th>Name <th>Email <th>City

<?php
$select_result = $sql->fetch($select->run());

foreach ($select_result as $row){
  echo "<tr><td>". $row['id'] ."<td>". $row['name'] ."<td>". $row['mail'] ."<td>". $row['city'] ."\n";
}

?>
</table>
  </body>
</html>