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
 * Representation of database connection.
 *
 * Common configuration: (see also driver specific configuration)
 * - tablePrefix => prefix for table names
 * - lazy (bool) => If TRUE, connection will be established only when required.
 * - detectTypes (bool) => Detect column types automatically
 * - formatDateTime => Date/time format ("U" for timestamp. If empty, DateTime object used).
 * - rowClass => Name of class to use as a row class.
 * - observer => Instance of INeevoObserver for profiling.
 *
 * @author Martin Srank
 * @package Neevo
 */
class NeevoConnection implements INeevoObservable {


	/** @var array */
	private $config;

	/** @var bool */
	private $connected = false;

	/** @var INeevoDriver */
	private $driver;

	/** @var NeevoStmtParser */
	private $stmtParser;

	/** @var SplObjectStorage */
	private $observers;

	/** @var INeevoCache */
	private $cache;


	/**
	 * Establish a connection.
	 * @param array|string|Traversable $config
	 * @param INeevoCache $cache
	 * @return void
	 * @throws InvalidArgumentException
	 */
	public function __construct($config, INeevoCache $cache = null){
		$this->observers = new SplObjectStorage;

		$this->cache = $cache !== null ? $cache : new NeevoCache;

		// Parse config
		if(is_string($config)){
			parse_str($config, $config);
		} elseif($config instanceof Traversable){
			$config = iterator_to_array($config);
		} elseif(!is_array($config)){
			throw new InvalidArgumentException('Configuration must be an array, string or instance of Traversable.');
		}

		// Default values
		$defaults = array(
			'driver' => Neevo::$defaultDriver,
			'lazy' => false,
			'tablePrefix' => '',
			'formatDateTime' => '',
			'detectTypes' => false,
			'rowClass' => 'NeevoRow',
			'observer' => null
		);

		// Create aliases
		self::alias($config, 'driver', 'extension');
		self::alias($config, 'username', 'user');
		self::alias($config, 'password', 'pass');
		self::alias($config, 'password', 'pswd');
		self::alias($config, 'host', 'hostname');
		self::alias($config, 'host', 'server');
		self::alias($config, 'database', 'db');
		self::alias($config, 'database', 'dbname');
		self::alias($config, 'tablePrefix', 'table_prefix');
		self::alias($config, 'tablePrefix', 'prefix');
		self::alias($config, 'charset', 'encoding');
		self::alias($config, 'observer', 'profiler');

		$config += $defaults;

		$this->setDriver($config['driver']);

		if($config['observer'] instanceof INeevoObserver){
			$this->attachObserver($config['observer']);
		}

		$this->config = $config;

		if($config['lazy'] === false){
			$this->connect();
		}
	}


	/**
	 * Perform connection.
	 * @return void
	 */
	public function connect(){
		if($this->connected === false){
			$this->driver->connect($this->config);
			$this->connected = true;

			$this->notifyObservers(INeevoObserver::CONNECT);
		}
	}


	/**
	 * Get configuration.
	 * @param string $key
	 * @return mixed
	 */
	public function getConfig($key = null){
		if($key === null){
			return $this->config;
		}
		return isset($this->config[$key]) ? $this->config[$key] : null;
	}


	/**
	 * Get defined table prefix.
	 * @return string
	 */
	public function getPrefix(){
		return isset($this->config['tablePrefix']) ? $this->config['tablePrefix'] : '';
	}


	/**
	 * Get the current driver instance.
	 * @return INeevoDriver
	 */
	public function getDriver(){
		return $this->driver;
	}


	/**
	 * Get the current statement parser instance.
	 * @return NeevoStmtParser
	 */
	public function getStmtParser(){
		return $this->stmtParser;
	}


	/**
	 * Get the current cache storage instance.
	 * @return INeevoCache
	 */
	public function getCache(){
		return $this->cache;
	}


	/**
	 * Set the cache storage.
	 * @param INeevoCache $cache
	 */
	public function setCache(INeevoCache $cache){
		$this->cache = $cache;
	}


	/**
	 * Attach given observer.
	 * @param INeevoObserver $observer
	 * @return void
	 */
	public function attachObserver(INeevoObserver $observer){
		$this->observers->attach($observer);
	}


	/**
	 * Detach given observer.
	 * @param INeevoObserver $observer
	 * @return void
	 */
	public function detachObserver(INeevoObserver $observer){
		$this->observers->detach($observer);
	}


	/**
	 * Notify all attached observers.
	 * @param int $event
	 * @return void
	 */
	public function notifyObservers($event){
		$args = func_get_args();
		array_unshift($args, $this);
		foreach($this->observers as $observer){
			call_user_func_array(array($observer, 'updateStatus'), $args);
		}
	}


	/*	************	Internal methods ************	*/


	/**
	 * Create an alias for configuration value.
	 * @param array $config Passed by reference
	 * @param string $key
	 * @param string $alias Alias of $key
	 * @return void
	 */
	public static function alias(&$config, $key, $alias){
		if(isset($config[$alias]) && !isset($config[$key])){
			$config[$key] = $config[$alias];
		}
	}


	/**
	 * Set the driver and statement parser.
	 * @param string $driver
	 * @return void
	 * @throws NeevoException
	 */
	private function setDriver($driver){
		$class = "NeevoDriver$driver";

		if(!$this->isDriver($class)){
			include_once dirname(__FILE__) . '/drivers/'.strtolower($driver).'.php';

			if(!$this->isDriver($class)){
				throw new NeevoException("Unable to create instance of Neevo driver '$driver'.");
			}
		}

		$this->driver = new $class;

		// Set statement parser
		if(in_array('NeevoStmtParser', class_parents($class))){
			$this->stmtParser = $this->driver;
		} else{
			$this->stmtParser = new NeevoStmtParser;
		}
	}


	/**
	 * Check wether the given class is valid Neevo driver.
	 * @param string $class
	 * @return bool
	 */
	private function isDriver($class){
		return (class_exists($class) && in_array('INeevoDriver', class_implements($class)));
	}


}
