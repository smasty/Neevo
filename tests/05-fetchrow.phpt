--TEST--
NeevoResult->fetchRow()
--FILE--
<?php

print_r($db->select(':id, :name, :url', 'author')
           ->order('id')
           ->limit(1)
           ->fetch()
             ->toArray());

?>
--EXPECT--
Array
(
    [id] => 11
    [name] => Linus Torvalds
    [url] => http://en.wikipedia.org/wiki/Linus_Torvalds
)
