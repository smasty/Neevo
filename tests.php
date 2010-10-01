<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <title>Neevo DAL Demo</title>
    <style>
      table, td, th{border:1px solid #555;border-collapse:collapse}
      td, th{padding:2px 10px;text-align:left}
      th{background:#ddd}
      #logfile{height:150px;overflow:auto;border:1px solid #555}
      code{display:block}
      table code{display:inline;margin:0}
    </style>
  </head>

  <body>

    <h1>Neevo DAL Demo</h1>

<pre><?php

// NetteDebug for debugging (http://nette.org)
include "debug.php";
Debug::enable();
//Debug::$strictMode = TRUE;


include "neevo.php";


// Initialize Neevo and set driver to MySQL
$sql = new Neevo('MySQL');


// Set Neevo error reporting level
$sql->set_error_reporting(Neevo::E_STRICT);


// Create connection to a database server
$sql->connect(array(
  'host'         => 'localhost',
  'username'     => 'root',
  'password'     => '',
  'database'     => 'neevo_demo',
  'encoding'     => 'utf8',
  'table_prefix' => 'dp_'
));


// Using  "WHERE col IN (val1, val2, ...)" construction
$s = $sql->select("client.id", 'neevo_demo.client')
         ->where("md5(client.id)", null, "or")
         ->where("client.name", array("John Doe", "Giacomo Doyle", "Justin Hicks"))
         ->limit(5)->dump()->fetch();

print_r($s);

// Data for Insert query demos
$insert_data = array(
  'name'  => 'John-Doe',
  'mail' => 'john.doe@example.com',
  'city' => 'Springfield',
  'about' => 'Lorem ipsum dolorem...',
  'friends'  => 23
);

// Data for Update query demos
$update_data = array(
  'name'  => 'John Doe',
  'mail' => 'john@doe.name',
  'city' => "Washington's DC"
);

// INSERT QUERY
$insert = $sql->insert('neevo_demo.client', $insert_data);

// Echo highlighted query
$insert->dump();

echo " Now unset 'city' column:\n";

// Unset 'city' column from values
$insert->undo('value', 'city');

$insert->dump();

// Run query;
$insert_id = $insert->insert_id();
echo " LAST_INSERT_ID: $insert_id\n\n";


// UPDATE QUERY
$update = $sql->update('client', $update_data)
              ->where('name', 'John-Doe')
              ->where('id !=', 101)
              ->order('id DESC');

$update_resource = $update->run();

$update->dump();

// DELETE QUERY
$delete = $sql->delete('client')
              ->where('mail', 'john@doe.name')
              ->order('id DESC')
              ->limit(1);

$delete_resource = $delete->run();

$delete->dump();


// SELECT ONE VALUE
$select_one = $sql->select('name', 'client')
                  ->where('id', 101);

$select_one->dump();

echo " Result: ". $select_one->fetch() ."\n\n";

// SELECT QUERY
$select = $sql->select('id, name, mail, city, MD5(mail) as mail_hash', 'client')
              ->where('name !=', 'Fuller Strickland', 'OR')
              ->where('name', 'John Doe')
              ->order('id DESC', 'name ASC')
              ->limit(10);

// Seek to 3rd row of resource (counting from zero)
$select->seek(2);

// Fetch results
$select_result = $select->fetch();

$select->dump();

?>

 Results:</pre>
<table border=0><tr><th>ID <th>Name <th>Email <th>City <th>E-mail hash

<?php

// Loop and print results
foreach ($select_result as $row){
  echo "<tr><td>". $row['id'] ."<td>". $row['name'] ."<td>". $row['mail'] ."<td>". $row['city'] ."<td><code>". $row['mail_hash'] ."\n";
}

?>
</table>
  </body>
</html>
