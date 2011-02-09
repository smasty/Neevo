--TEST--
NeevoResult->fetchPairs() key=>value test, key=>row test.
--FILE--
<?php

// Also check columns auto-adding
print_r($db->select(':url', 'author')->order(':id')->limit(2)->fetchPairs('id', 'name'));

foreach($db->select('author')->order(':id')->limit(2)->fetchPairs('id') as $r){
  print_r($r->toArray());
}
?>
--EXPECT--
Array
(
    [11] => Linus Torvalds
    [12] => Dries Buytaert
)
Array
(
    [id] => 11
    [name] => Linus Torvalds
    [url] => http://en.wikipedia.org/wiki/Linus_Torvalds
)
Array
(
    [id] => 12
    [name] => Dries Buytaert
    [url] => http://buytaert.net
)
