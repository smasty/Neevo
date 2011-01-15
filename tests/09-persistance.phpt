--TEST--
Persistance - insert/update/delete
--FILE--
<?php

// Insert
$db->insert('software',
  array(
    'id' => 5,
    'title' => 'Debian',
    'web' => 'http://debian.org'
  ));

// Check insertion
echo $db->select('title', 'software')->where('id', 5)->limit(1)->fetchSingle() . "\n";

// Update
$db->update('software', array('slogan' => 'Lorem Ipsum-'.  rand(111, 999)))->exec();
// Check update
echo substr($db->select('slogan', 'software')->where('id', 5)->fetchSingle(), 0, -3) . "\n";

// Delete
$db->delete('software')->where('id', 5)->exec();
// Check delete
echo $db->select('title', 'software')->where('id', 5)->fetchSingle() ? 'delete failed' : 'delete ok';

?>
--EXPECT--
Debian
Lorem Ipsum-
delete ok