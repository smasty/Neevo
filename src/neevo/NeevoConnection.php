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
 * - autoJoin (bool) => Experimental! Automatically create LEFT JOINs when selecting from more tables.
 *
 * @author Martin Srank
 * @package Neevo
 */
class NeevoConnection implements INeevoObservable, ArrayAccess {


	/** @var array */
	private $config;

	/** @var bool */
	private $connected = false;

	/** @var INeevoDriver */
	private $driver;

	/** @var string */
	private $parser = 'NeevoParser';

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
			'observer' => null,
			'autoJoin' => false
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
	 * Get the current parser class name.
	 * @return string
	 */
	public function getParser(){
		return $this->parser;
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


	/*  ************  Implementation of INeevoObservable  ************  */


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


	/*  ************  Implementation of ArrayAccess  ************  */


	/**
	 * Get configuration value.
	 * @param string $key
	 * @return mixed
	 */
	public function offsetGet($key){
		return $this->getConfig($key);
	}


	/**
	 * Check if configuration value exists.
	 * @param mixed $key
	 * @return bool
	 */
	public function offsetExists($key){
		return isset($this->config[$key]);
	}


	/** @internal */
	public function offsetSet($offset, $value){}

	/** @internal */
	public function offsetUnset($offset){}


	/*  ************  Internal methods  ************  */


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
	 * @throws NeevoDriverException
	 */
	private function setDriver($driver){
		$class = "NeevoDriver$driver";

		if(!class_exists($class)){
			$file = dirname(__FILE__) . '/drivers/' . strtolower($driver) . '.php';

			if(!file_exists($file)){
				throw new NeevoDriverException("$driver driver file ($file) does not exist.");
			}
			if(is_readable($file)){
				include_once $file;
			} else{
				throw new NeevoDriverException("$driver driver file ($file) is not readable.");
			}
		}
		if(!$this->isDriver($class)){
			throw new NeevoDriverException("Class '$class' is not a valid Neevo driver class.");
		}

		$this->driver = new $class;

		// Set statement parser
		if($this->isParser($class)){
			$this->parser = $class;
		}
	}


	/**
	 * Check wether the given class is valid Neevo driver.
	 * @param string $class
	 * @return bool
	 */
	private function isDriver($class){
		try{
			$reflection = new ReflectionClass($class);
			return $reflection->implementsInterface('INeevoDriver');
		} catch(ReflectionException $e){
			return false;
		}
	}


	/**
	 * Check wether the given class is valid Neevo statement parser.
	 * @param string $class
	 * @return bool
	 */
	private function isParser($class){
		try{
			$reflection = new ReflectionClass($class);
			return $reflection->isSubclassOf('NeevoParser');
		} catch(ReflectionException $e){
			return false;
		}
	}


}
