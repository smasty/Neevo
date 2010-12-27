--TEST--
NeevoRow Update/delete functionality
--FILE--
<?php

$db->insert('user', array(
  'mail' => 'john@example.com'
))->run();

$result = $db->select('user')->limit(1);
$result[0]->mail = 'doe@example.com';

// Update
echo $result[0]->update() ? "update ok\n" : "update failed\n";

// Delete
echo $result[0]->delete() ? "delete ok\n" : "delete failed\n";
?>
--EXPECT--
update ok
delete ok
