--TEST--
NeevoResult->fetchSingle()
--FILE--
<?php

require __DIR__ . '/../../src/neevo.php';
$db = new Neevo(array(
	'driver' => 'sqlite',
	'file' => __DIR__ . '/sqlite.db'
));

echo $db->select(':name', 'author')->where(':id', 11)->limit(1)->fetchSingle() . "\n";

?>
--EXPECT--
Linus Torvalds
