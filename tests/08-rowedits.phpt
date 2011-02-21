--TEST--
NeevoRow Update/delete functionality
--FILE--
<?php

$db->insert('software', array(
  'title' => 'Debian Linux',
  'author_id' => 11,
  'url' => 'http://example.com'
))->run();

$row = $db->select('software')->where('title', 'Debian Linux')->fetch();
$row->url = 'http://debian.org';

// Update
echo $row->update() ? "update ok\n" : "update failed\n";

// Delete
echo $row->delete() ? "delete ok\n" : "delete failed\n";

unset($row);
?>
--EXPECT--
update ok
delete ok
