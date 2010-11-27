--TEST--
NeevoResult->fetchRow()
--FILE--
<?php

print_r($db->select('id, name, web', 'author')->order('id')->limit(1)->fetchRow(Neevo::ASSOC));

?>
--EXPECT--
Array
(
    [id] => 11
    [name] => Martin Srank
    [web] => http://smasty.net
)
