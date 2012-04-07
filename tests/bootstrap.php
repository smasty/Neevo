<?php

require_once __DIR__ . '/../src/loader.php';


// Add PEAR to include path for Travis CI
if(!class_exists('PEAR_RunTest'))
	set_include_path(get_include_path() . PATH_SEPARATOR . '../lib/pear-core');


// Test helper objects autoloader
spl_autoload_register(function($class){
	$class = str_replace('\\', '_', $class);

	$path = __DIR__ . "/mocks/$class.php";
	if(file_exists($path))
		return require_once $path;
	return false;
});
