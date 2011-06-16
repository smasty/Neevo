<?php

require __DIR__ . '/../../src/neevo.php';

$db = new Neevo(array(
	'driver' => 'sqlite',
	'file' => __DIR__ . '/sqlite.db'
));