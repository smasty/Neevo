--TEST--
Basic Join test
--FILE--
<?php

foreach($db->select('author')->leftJoin('software', 'author.id = software.aid') as $r)
  echo "$r->name - $r->title\n";

?>
--EXPECT--
Martin Srank - Neevo
Martin Srank - Blabshare
Linus Torvalds - Linux kernel
Linus Torvalds - Git
