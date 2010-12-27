--TEST--
NeevoResult->fetchPairs() key=>value test, key=>row test.
--FILE--
<?php

// Also check columns auto-adding
print_r($db->select('web', 'author')->order('id')->limit(2)->fetchPairs('id', 'name'));

print_r($db->select('author')->order('id')->limit(2)->fetchPairs('id'));
?>
--EXPECT--
Array
(
    [11] => Martin Srank
    [12] => Linus Torvalds
)
Array
(
    [11] => Array
        (
            [id] => 11
            [name] => Martin Srank
            [web] => http://smasty.net
        )

    [12] => Array
        (
            [id] => 12
            [name] => Linus Torvalds
            [web] => http://torvalds-family.blogspot.com
        )

)
