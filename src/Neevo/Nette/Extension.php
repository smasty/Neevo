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

use Nette\Config\CompilerExtension;


/**
 * Neevo extension for Nette Framework.
 * Creates services `manager`, `panel` and `cache`.
 */
class Extension extends CompilerExtension {


	public function loadConfiguration(){
		$container = $this->getContainerBuilder();
		$config = $this->getConfig();

		// Config
		$explain = isset($config['explain'])
			? $config['explain']
			: !$container->parameters['productionMode'];
		unset($config['explain']);

		$panelEvents = $container->parameters['productionMode']
			? DebugPanel::EXCEPTION
			: DebugPanel::QUERY + DebugPanel::EXCEPTION;

		// Cache
		$container->addDefinition($this->prefix($c = 'cache'))
			->setClass('Neevo\Nette\CacheAdapter', array(ucfirst($this->prefix(ucfirst($c)))));

		// Manager
		$manager = $container->addDefinition($this->prefix('manager'))
			->setClass('Neevo\Manager', array($config));

		// Panel
		$panel = $container->addDefinition($this->prefix('panel'))
			->setClass('Neevo\Nette\DebugPanel')
			->addSetup('$service->setExplain(?)', $explain)
			->addSetup('Nette\Diagnostics\Debugger::$bar->addPanel(?)', array('@self'))
			->addSetup('Nette\Diagnostics\Debugger::$blueScreen->addPanel(?)', array(array('@self', 'renderException')));

		$manager->addSetup('$service->attachObserver(?, ?)', array($panel, $panelEvents));
	}


}
