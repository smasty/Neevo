<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <title>Neevo MySQL layer Demo</title>
    <style>
      table, td, th{border:1px solid #555;border-collapse:collapse}
      td, th{padding:2px 10px;text-align:left}
      th{background:#ddd}
      #logfile{height:150px;overflow:auto;border:1px solid #555}
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

// Log queries to file
$sql->log(true, 'neevo.log');

// Turn Neevo error reporting ON (0 for OFF, default: ON)
$sql->errors(1);

// Set table pefix to "dp_"
$sql->prefix('dp_');



// Data for Insert query demos
$insert_data = array(
  'name'  => 'John Doe',
  'mail' => 'john.doe@example.com',
  'city' => 'Springfield',
  'about' => 'Lorem ipsum dolorem...',
  'friends'  => 23
);

// Data for Update query demos
$update_data = array(
  'name'  => 'John Doe',
  'mail' => 'john@doe.name',
  'city' => 'Washington DC'
);

// INSERT QUERY
$insert = $sql->insert('client', $insert_data);

// Echo highlighted query
$insert->dump();

echo "\n Now unset 'city' column:\n\n";

$insert->undo('value', 'city');

$insert->dump();

// Run it
$insert_resource = $insert->run();
// Get info about query
echo $insert->info(true). "\n\n";

// UPDATE QUERY
$update = $sql->update('client', $update_data)->where('name', 'John Doe')->where('id !=', 101)->order('id DESC');
$update->dump();
$update_resource = $update->run();

echo $update->info(true). "\n\n";

// Do not logto file from here
$sql->log(false);

// DELETE QUERY
$delete = $sql->delete('client')->where('mail', 'john@doe.name')->order('id DESC')->limit(1);
$delete->dump();
$delete_resource = $delete->run();

echo $delete->info(true). "\n\n";


// SELECT ONE VALUE
$select_one = $sql->select('name', 'client')->where('id', 101);
$select_one->dump();

echo " Result: ". $sql->result( $select_one->run() ) ."\n\n";

// Log to file again
$sql->log(true);

// SELECT QUERY
$select = $sql->select('id, name, mail, city, MD5(mail) as mail_hash', 'client')->where('name !=', 'Fuller Strickland', 'OR')->where('name', 'John Doe')->order('id DESC', 'mail ASC', 'name')->limit(10);
$select->dump();
$select_resource = $select->run();

$select_result = $sql->fetch($select_resource);

echo $select->info(true);
?>

 Results:</pre>
<table border=0><tr><th>ID <th>Name <th>Email <th>City <th>E-mail hash

<?php


foreach ($select_result as $row){
  echo "<tr><td>". $row['id'] ."<td>". $row['name'] ."<td>". $row['mail'] ."<td>". $row['city'] ."<td><code>". $row['mail_hash'] ."\n";
}

?>
</table>

    <pre>

LOG file:
<div id="logfile">
<?php echo file_get_contents($sql->log_file); ?>
</div>

    </pre>

  </body>
</html>
