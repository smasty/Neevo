--TEST--
NeevoResult->fetchSingle()
--FILE--
<?php

echo $db->select(':name', 'author')->where(':id', 11)->limit(1)->fetchSingle() . "\n";

?>
--EXPECT--
Linus Torvalds
