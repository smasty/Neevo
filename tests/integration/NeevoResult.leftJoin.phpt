--TEST--
Basic Join test
--FILE--
<?php

require __DIR__ . '/../../src/neevo.php';
$db = new Neevo(array(
	'driver' => 'sqlite',
	'file' => __DIR__ . '/sqlite.db'
));

foreach($db->select('author')
			->leftJoin(':software', ':author.id = :software.author_id')
			->order(':software.id', Neevo::ASC)
		as $r){
	echo "$r->name - $r->title\n";
}

?>
--EXPECT--
Linus Torvalds - Linux kernel
Linus Torvalds - Git
Dries Buytaert - Drupal
Dries Buytaert - Acquia
David Grudl - Nette Framework
David Grudl - Texy!
