--TEST--
Basic Join test
--FILE--
<?php

foreach($db->select('author')->leftJoin('software')->on('author.id = software.aid') as $r){
  echo "$r->name - $r->title\n";
}

echo "--\n";

foreach($db->select('author')->rightJoin('software')->on('author.id = software.aid') as $r){
  echo "$r->name - $r->title\n";
}

?>
--EXPECT--
Martin Srank - Neevo
Martin Srank - Blabshare
Linus Torvalds - Linux kernel
Linus Torvalds - Git
--
Martin Srank - Neevo
Linus Torvalds - Linux kernel
Martin Srank - Blabshare
Linus Torvalds - Git
