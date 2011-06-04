--TEST--
Persistance - insert/update/delete
--FILE--
<?php

require __DIR__ . '/../../src/neevo.php';
$db = new Neevo(array(
	'driver' => 'sqlite',
	'file' => __DIR__ . '/sqlite.db'
));

// Insert
$db->insert('software',
	array(
		'title' => 'dibi',
		'author_id' => 13,
		'url' => 'http://dibiphp.com'
	))->run();

// Check insertion
echo $db->select(':url', 'software')
		->where('title', 'dibi')
		->limit(1)
		->fetchSingle() . "\n";

// Update
$db->update('software', array('title' => 'dibi database layer'))
	->where('title', 'dibi')
	->run();

// Check update
echo $db->select(':title', 'software')
		->where('url', 'http://dibiphp.com')
		->fetchSingle() . "\n";

// Delete
$db->delete('software')
	->where('url', 'http://dibiphp.com')
	->run();

// Check delete
echo $db->select(':title', 'software')
		->where('url', 'http://dibiphp.com')
		->fetchSingle()
		? 'delete failed' : 'delete ok';

echo "\n";
?>
--EXPECT--
http://dibiphp.com
dibi database layer
delete ok
