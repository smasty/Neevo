--TEST--
NeevoResult->fetchPairs() key=>value test, key=>row test.
--FILE--
<?php

// Also check columns auto-adding
print_r($db->select('web', 'author')->order('id')->limit(2)->fetchPairs('id', 'name'));

foreach($db->select('author')->order('id')->limit(2)->fetchPairs('id') as $r){
  print_r($r->toArray());
}
?>
--EXPECT--
Array
(
    [11] => Martin Srank
    [12] => Linus Torvalds
)
Array
(
    [id] => 11
    [name] => Martin Srank
    [web] => http://smasty.net
)
Array
(
    [id] => 12
    [name] => Linus Torvalds
    [web] => http://torvalds-family.blogspot.com
)
