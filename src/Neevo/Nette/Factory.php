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

use Neevo\Manager;
use Nette\Caching\IStorage;
use Nette\Diagnostics\Debugger;


/**
 * Factory class for Nette Framework Dependency Injection Container.
 */
class Factory {


	/**
	 * Neevo DI container factory.
	 * @param array $config Neevo configuration options
	 * @param IStorage $cacheStorage Nette CacheStorage service (autowired)
	 * @return Manager
	 */
	public static function createService(array $config, IStorage $cacheStorage){
		$neevo = new Manager((array) $config, new CacheAdapter($cacheStorage));

		// Setup Debug panel
		$panel = new DebugPanel(isset($config['explain']) ? $config['explain'] : !Debugger::$productionMode);
		$neevo->attachObserver($panel, Debugger::$productionMode ? DebugPanel::EXCEPTION : DebugPanel::QUERY + DebugPanel::EXCEPTION);

		// Register Debug panel
		if(!Debugger::$productionMode)
			Debugger::$bar->addPanel($panel);

		// Register Bluescreen panel
		Debugger::$blueScreen->addPanel(callback($panel, 'renderException'));

		return $neevo;
	}


}
