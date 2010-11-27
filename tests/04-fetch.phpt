--TEST--
NeevoResult->fetch() and iterating
--FILE--
<?php

foreach($db->select('id, name', 'author')->order('id')->limit(2) as $row){
  echo "$row->name ($row->id)\n";
}

?>
--EXPECT--
Martin Srank (11)
Linus Torvalds (12)
