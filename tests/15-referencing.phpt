--TEST--
Referencing rows (1:N)
--FILE--
<?php

foreach($db->select('software')->order(':id') as $sw){
	echo "$sw->title - {$sw->author()->name}\n";
}

?>
--EXPECT--
Linux kernel - Linus Torvalds
Git - Linus Torvalds
Drupal - Dries Buytaert
Acquia - Dries Buytaert
Nette Framework - David Grudl
Texy! - David Grudl
