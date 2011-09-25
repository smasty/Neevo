<?php
/**
 * Neevo - Tiny database layer for PHP. (http://neevo.smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * Copyright (c) 2011 Martin Srank (http://smasty.net)
 *
 */

namespace Neevo\Nette;

use Neevo,
	Nette\DI\Container;


/**
 * Neevo factory class for Nette Framework Dependency Injection Container.
 */
class ServiceFactory {


	/**
	 * Neevo DI container factory.
	 * @param Container $container
	 * @param array $config Configuration options
	 * @param bool $explain Run EXPLAIN on all queries?
	 * @return Neevo\Manager
	 */
	public static function createService(Container $container, array $config = null, $explain = true){
		$neevo = new Neevo\Manager((array) $config, new CacheAdapter($container->cacheStorage));
		DebugPanel::register($neevo, $explain);
		return $neevo;
	}


}
