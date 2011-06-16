--TEST--
GROUP BY testing
--FILE--
<?php
require_once __DIR__ . '/config.php';

foreach($db->select(':author_id, MAX(:id) as :max', 'software')
			->group(':author_id')
			->order(':author_id', Neevo::ASC) as $r){
	echo "$r->author_id-$r->max\n";
}

?>
--EXPECT--
11-2
12-4
13-6
