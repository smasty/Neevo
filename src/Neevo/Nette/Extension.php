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

		$panelEvents = $container->parameters['productionMode']
			? DebugPanel::EXCEPTION
			: DebugPanel::QUERY + DebugPanel::EXCEPTION;

		// Cache
		$cache = $container->addDefinition($this->prefix($c = 'cache'))
			->setClass('Neevo\Nette\CacheAdapter', array(ucfirst($this->prefix($c))));

		// Manager
		$manager = $container->addDefinition($this->prefix('manager'))
			->setClass('Neevo\Manager', array($config, $this->prefix('@cache')));

		// Panel
		$panel = $container->addDefinition($this->prefix('panel'))
			->setClass('Neevo\Nette\DebugPanel')
			->addSetup('$service->setExplain(?)', !$container->parameters['productionMode'])
			->addSetup('Nette\Diagnostics\Debugger::$bar->addPanel(?, ?)', array('@self', strtr('.', '-', $this->prefix('panel'))))
			->addSetup('Nette\Diagnostics\Debugger::$blueScreen->addPanel(?)', array(array('@self', 'renderException')));

		$manager->addSetup('$service->attachObserver(?, ?)', array($panel, $panelEvents));
	}


}
