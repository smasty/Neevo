--TEST--
NeevoResult->fetch() and iterating
--FILE--
<?php

$arr = db()->select('id, name', 'author')->order('id')->limit(2);

foreach($arr as $row){
  echo "$row->name ($row->id)\n";
}

?>
--EXPECT--
Martin Srank (11)
Linus Torvalds (12)
