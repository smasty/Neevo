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
use Nette\DI\Container;


/**
 * Neevo factory class for Nette Framework Dependency Injection Container.
 */
class NeevoFactory {


	/**
	 * Neevo DI container factory.
	 * @param Container $container
	 * @return Neevo
	 */
	public static function createService(Container $container, array $config = null, $explain = true){
		$neevo = new Neevo((array) $config, new NeevoCacheNette($container->cacheStorage));
		NeevoPanel::register($neevo, $explain);
		return $neevo;
	}


}
