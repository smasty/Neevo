<?php

require_once __DIR__ . '/../src/neevo.php';


// Test helper objects autoloader
function NeevoTestAutoload($class){
	$path = __DIR__ . "/mocks/$class.php";
	if(file_exists($path))
		return require_once $path;
	return false;
}

spl_autoload_register('NeevoTestAutoload');