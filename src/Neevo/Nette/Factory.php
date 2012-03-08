<?php
/**
 * Neevo - Tiny database layer for PHP. (http://neevo.smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * Copyright (c) 2012 Smasty (http://smasty.net)
 *
 */

namespace Neevo\Nette;

use Neevo,
	Nette;


/**
 * Factory class for Nette Framework Dependency Injection Container.
 */
class Factory {


	/**
	 * Neevo DI container factory.
	 * @return Neevo\Manager
	 */
	public static function createService(Nette\Caching\IStorage $cacheStorage, array $config){
		$neevo = new Neevo\Manager((array) $config, new Cache($cacheStorage));
		DebugBar::register($neevo);
		return $neevo;
	}


}
