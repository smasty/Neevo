--TEST--
NeevoResult->fetch() and iterating
--FILE--
<?php
require_once __DIR__ . '/config.php';

foreach($db->select(':id, :name', 'author')->order(':id')->limit(2) as $row){
	echo "$row->name ($row->id)\n";
}

?>
--EXPECT--
Linus Torvalds (11)
Dries Buytaert (12)
