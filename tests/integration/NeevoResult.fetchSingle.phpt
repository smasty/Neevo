--TEST--
Neevo\Result->fetchSingle()
--FILE--
<?php
require_once __DIR__ . '/config.php';

echo $db->select(':name', 'author')->where(':id', 11)->limit(1)->fetchSingle() . "\n";

?>
--EXPECT--
Linus Torvalds
