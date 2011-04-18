--TEST--
Subquery support
--FILE--
<?php

foreach($db->select('software')
	->where(':id IN %sub',
		$db->select('id', 'software')
		->where(':id < %i', 3))
	as $r)
{
	echo "$r->title\n";
}

?>
--EXPECT--
Linux kernel
Git
