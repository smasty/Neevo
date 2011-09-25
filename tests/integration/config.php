<?php

require __DIR__ . '/../../src/loader.php';

$db = new Neevo\Manager(array(
	'driver' => 'sqlite3',
	'memory' => true
));

// Load database to the memory
$db->loadFile(__DIR__ . '/db.sql');
