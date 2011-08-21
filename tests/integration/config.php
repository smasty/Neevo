<?php

require __DIR__ . '/../../src/neevo.php';

$db = new Neevo(array(
	'driver' => 'sqlite3',
	'memory' => true
));

// Load database to the memory
$db->loadFile(__DIR__ . '/db.sql');
