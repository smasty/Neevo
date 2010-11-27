--TEST--
NeevoResult->fetchAssoc();
--FILE--
<?php

print_r($db->select('id, title', 'software')->order('id')->limit(3)->fetchAssoc('id', Neevo::ASSOC));

?>
--EXPECT--
Array
(
    [1] => Array
        (
            [id] => 1
            [title] => Neevo
        )

    [2] => Array
        (
            [id] => 2
            [title] => Linux kernel
        )

    [3] => Array
        (
            [id] => 3
            [title] => Blabshare
        )

)
