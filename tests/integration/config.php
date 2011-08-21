<?php

require __DIR__ . '/../../src/neevo.php';

$db = new Neevo(array(
	'driver' => 'sqlite3',
	'file' => ':memory:'
));

// Load database to the memory
$db->loadFile(__DIR__ . '/db.sql');
