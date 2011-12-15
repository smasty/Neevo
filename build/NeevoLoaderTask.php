#!/usr/bin/env php
<?php
/**
 * A Phing task for reloading the class list in Neevo\Loader.
 * Put this file in the PHP_INCLUDE_PATH/phing/tasks/smasty/ directory.
 */

require_once 'phing/Task.php';

// Load Nette Framework (pear.nette.org/Nette)
require_once 'Nette/loader.php';


class NeevoLoaderTask extends Task {


	private $directory, $file = 'Loader.php';


	public function setDirectory($directory){
		$this->directory = realpath($directory);
	}


	public function setFile($file){
		$this->file = $file;
	}


	public function init(){

	}


	public function main(){
		echo "Rebuilding class list in Neevo\\Loader...";
		// Prepare RobotLoader
		file_put_contents("$this->directory/netterobots.txt", "Disallow: Drivers/\nDisallow: Loader.php");
		$loader = new Nette\Loaders\RobotLoader();
		$loader->setCacheStorage(new Nette\Caching\Storages\DevNullStorage);
		$loader->addDirectory($this->directory);
		$loader->rebuild();
		unlink("$this->directory/netterobots.txt");

		// Create list of classes
		$types = array();
		foreach($loader->getIndexedClasses() as $class => $classpath){
			$types[strtolower(str_replace('\\', '\\\\', $class))] = substr($classpath, strlen($this->directory));
		}
		ksort($types);


		// Format generated code
		$list = var_export($types, true);
		$list = str_replace('array (', 'array(', $list);
		$list = str_replace(')', "\t)", $list);
		$list = str_replace("  '", "\t\t'", $list);

		$script = file_get_contents("$this->directory/$this->file");
		$script = preg_replace('~= array.*\)~sU', "= $list", $script, 1, $count);

		if($count !== 1){
			throw new Exception('Injecting to Neevo\\Loader failed.');
		}

		file_put_contents("$this->directory/$this->file", $script);
		echo "done.\n";
	}


}