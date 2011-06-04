--TEST--
SELECT with only table name passed.
--FILE--
<?php

require __DIR__ . '/../../src/neevo.php';
$db = new Neevo(array(
	'driver' => 'sqlite',
	'file' => __DIR__ . '/sqlite.db'
));

echo implode(',', array_keys($db->select('software')
	->order(':id')
	->limit(1)
	->fetch()
	->toArray())) . "\n";

try{
	$db->select();
} catch(InvalidArgumentException $e){
	echo "catched\n";
}
?>
--EXPECT--
id,author_id,title,url
catched
