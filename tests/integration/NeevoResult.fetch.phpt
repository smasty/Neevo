--TEST--
NeevoResult->fetch() and iterating
--FILE--
<?php

require __DIR__ . '/../../src/neevo.php';
$db = new Neevo(array(
	'driver' => 'sqlite',
	'file' => __DIR__ . '/sqlite.db'
));

foreach($db->select(':id, :name', 'author')->order(':id')->limit(2) as $row){
	echo "$row->name ($row->id)\n";
}

?>
--EXPECT--
Linus Torvalds (11)
Dries Buytaert (12)
