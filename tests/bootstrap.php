<?php

require_once __DIR__ . '/../src/loader.php';


// Test helper objects autoloader
spl_autoload_register(function($class){
	$class = str_replace('\\', '_', $class);

	$path = __DIR__ . "/mocks/$class.php";
	if(file_exists($path))
		return require_once $path;
	return false;
});
