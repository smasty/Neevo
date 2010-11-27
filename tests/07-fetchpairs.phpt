--TEST--
NeevoResult->fetchPairs()
--FILE--
<?php

// Also check columns auto-adding
print_r($db->select('web', 'author')->order('id')->limit(2)->fetchPairs('id', 'name'));

?>
--EXPECT--
Array
(
    [11] => Martin Srank
    [12] => Linus Torvalds
)
