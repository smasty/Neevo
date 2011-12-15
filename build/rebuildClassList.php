#!/usr/bin/env php
<?php

$dir = realpath($argv[1] . '/Neevo');
$file = 'Loader.php';
// Load Nette Framework from PEAR package
require_once 'Nette/loader.php';


// Prepare RobotLoader
file_put_contents("$dir/netterobots.txt", "Disallow: Drivers/\nDisallow: Loader.php");
$loader = new Nette\Loaders\RobotLoader();
$loader->setCacheStorage(new Nette\Caching\Storages\DevNullStorage);
$loader->addDirectory($dir);
$loader->rebuild();
unlink("$dir/netterobots.txt");

// Create list of classes
$types = array();
foreach($loader->getIndexedClasses() as $class => $classpath){
	$types[strtolower(str_replace('\\', '\\\\', $class))] = substr($classpath, strlen($dir));
}
ksort($types);


// Format generated code
$list = var_export($types, true);
$list = str_replace('array (', 'array(', $list);
$list = str_replace(')', "\t)", $list);
$list = str_replace("  '", "\t\t'", $list);

$script = file_get_contents("$dir/$file");
$script = preg_replace('~= array.*\)~sU', "= $list", $script, 1, $count);

if($count !== 1){
	throw new Exception('Injecting to Neevo\\Loader failed.');
}

file_put_contents("$dir/$file", $script);