--TEST--
NeevoRow Update/delete functionality
--FILE--
<?php

$db->insert('user', array(
  'mail' => 'john@example.com'
))->run();

$row = $db->select('user')->limit(1)->fetchRow();
$row->mail = 'doe@example.com';

// Update
echo $row->update() ? "update ok\n" : "update failed\n";

// Delete
echo $row->delete() ? "delete ok\n" : "delete failed\n";

unset($row);
?>
--EXPECT--
update ok
delete ok
