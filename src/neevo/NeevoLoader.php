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


/**
 * Autoloader responsible for loading Neevo classes and interfaces.
 * @author Martin Srank
 * @package Neevo
 */
class NeevoLoader {


	/** @var array */
	private $list = array(
		'ineevocache' => '/NeevoCache.php',
		'ineevodriver' => '/INeevoDriver.php',
		'ineevoobservable' => '/INeevoObservable.php',
		'ineevoobserver' => '/INeevoObserver.php',
		'neevo' => '/Neevo.php',
		'neevocache' => '/NeevoCache.php',
		'neevocachefile' => '/NeevoCache.php',
		'neevocachememcache' => '/NeevoCache.php',
		'neevocachesession' => '/NeevoCache.php',
		'neevoconnection' => '/NeevoConnection.php',
		'neevodriverexception' => '/NeevoException.php',
		'neevoexception' => '/NeevoException.php',
		'neevoimplementationexception' => '/NeevoException.php',
		'neevoliteral' => '/Neevo.php',
		'neevoobservermap' => '/NeevoObserverMap.php',
		'neevoparser' => '/NeevoParser.php',
		'neevoresult' => '/NeevoResult.php',
		'neevoresultiterator' => '/NeevoResultIterator.php',
		'neevorow' => '/NeevoRow.php',
		'neevostmt' => '/NeevoStmt.php',
		'neevostmtbase' => '/NeevoStmtBase.php',
	);

	/** @var NeevoLoader */
	private static $instance;


	private function __construct(){}


	/**
	* Get the singleton instance.
	* @return NeevoLoader
	*/
	public static function getInstance(){
		if(self::$instance === null){
			self::$instance = new self;
		}
		return self::$instance;
	}


	/**
	 * Register the autoloader.
	 * @return void
	 */
	public function register(){
		spl_autoload_register(array($this, 'tryLoad'));
	}


	/**
	 * Unregister the autoloader.
	 * @return void
	 */
	public function unregister(){
		spl_autoload_unregister(array($this, 'tryLoad'));
	}


	/**
	 * Try load Neevo class/interface.
	 * @param string $type
	 * @return bool
	 */
	public function tryLoad($type){
		$type = trim(strtolower($type), '\\');

		if(isset($this->list[$type])){
			return include_once dirname(__FILE__) . $this->list[$type];
		}
		return false;
	}


}
