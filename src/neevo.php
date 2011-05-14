<?php
/**
 * Neevo - Tiny database layer for PHP. (http://neevo.smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * Copyright (c) 2011 Martin Srank (http://smasty.net)
 *
 * @todo PHPUnit tests
 * @todo Support for Traversable everywhere where array() is needed.
 * @todo Fix NeevoResult subquery infinite loop.
 */


// PHP compatibility
if(version_compare(PHP_VERSION, '5.1.2', '<')){
	trigger_error('Neevo requires PHP version 5.1.2 or newer', E_USER_ERROR);
}


// Try to turn magic quotes off - Neevo handles SQL quoting.
@set_magic_quotes_runtime(false);


// Register autoloader responsible for loading Neevo classes and interfaces.
require_once dirname(__FILE__) . '/neevo/NeevoLoader.php';
NeevoLoader::getInstance()->register();
