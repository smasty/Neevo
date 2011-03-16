<?php
/**
 * Neevo - Tiny open-source database abstraction layer for PHP
 *
 * Copyright 2010-2011 Martin Srank (http://smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * @author   Martin Srank (http://smasty.net)
 * @license  http://neevo.smasty.net/license MIT license
 * @link     http://neevo.smasty.net/
 *
 */


// PHP compatibility
if(PHP_VERSION_ID < 50102){
	trigger_error('Neevo requires PHP version 5.1.2 or newer', E_USER_ERROR);
}


// Turn magic quotes off - Neevo handles SQL quoting.
@set_magic_quotes_runtime(false);


// Register autoloader responsible for loading Neevo classes and interfaces.
require_once dirname(__FILE__) . '/neevo/NeevoLoader.php';
NeevoLoader::getInstance()->register();
