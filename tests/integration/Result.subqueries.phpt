--TEST--
Subquery support
--FILE--
<?php
require_once __DIR__ . '/config.php';

foreach($db->select('software')
	->where(':id IN %sub',
		$db->select('id', 'software')
		->where(':id < %i', 3))
	as $r)
{
	echo "$r->title\n";
}

echo "---\n";

foreach($db->select($db->select('software')->as('insoft')) as $s){
	echo "$s->title\n";
}

?>
--EXPECT--
Linux kernel
Git
---
Linux kernel
Git
Drupal
Acquia
Nette Framework
Texy!
