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

namespace Neevo;

use ArrayAccess;
use InvalidArgumentException;
use Neevo\Cache\MemoryStorage;
use Neevo\Cache\StorageInterface;
use Neevo\DriverInterface;
use PDO;
use ReflectionClass;
use ReflectionException;
use SplObjectStorage;
use Traversable;


/**
 * Representation of database connection.
 *
 * Common configuration: (see also driver specific configuration)
 * - tablePrefix => prefix for table names
 * - lazy (bool) => If TRUE, connection will be established only when required.
 * - result
 *   - detectTypes (bool) => Detect column types automatically
 *   - formatDate => Date/time format (empty for DateTime instance).
 * - rowClass => Name of class to use as a row class.
 *
 * @author Smasty
 */
class Connection implements ObservableInterface, ArrayAccess {


	/** @var array */
	private $config;

	/** @var bool */
	private $connected = false;

	/** @var DriverInterface */
	private $driver;

	/** @var string */
	private $parser = 'Neevo\\Parser';

	/** @var SplObjectStorage */
	private $observers;

	/** @var StorageInterface */
	private $cache;


	/**
	 * Establishes a connection.
	 * @param array|string|Traversable|PDO $config
	 * @param StorageInterface $cache
	 * @throws InvalidArgumentException
	 */
	public function __construct($config, StorageInterface $cache = null){
		$this->observers = new SplObjectStorage;

		$this->cache = $cache !== null ? $cache : new MemoryStorage;

		// Parse config
		if(is_string($config)){
			parse_str($config, $config);
		} elseif($config instanceof Traversable){
			$tmp = array();
			foreach($config as $key => $val){
				$tmp[$key] = $val instanceof Traversable ? iterator_to_array($val) : $val;
			}
			$config = $tmp;
		} elseif(class_exists('PDO') && $config instanceof PDO){
			$config = array(
				'driver' => 'pdo',
				'pdo' => $config
			);
		} elseif(!is_array($config)){
			throw new InvalidArgumentException('Configuration must be an array, string or Traversable.');
		}

		// Default values
		$defaults = array(
			'driver' => Manager::$defaultDriver,
			'lazy' => true,
			'rowClass' => 'Neevo\\Row',
			'tablePrefix' => '',
			'result' => array(
				'detectTypes' => false,
				'formatDate' => '',
			),
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
		self::alias($config, 'result.detectTypes', 'detectTypes');
		self::alias($config, 'result.formatDate', 'formatDateTime');

		$config = array_replace_recursive($defaults, $config);

		$this->setDriver($config['driver']);

		$config['lazy'] = (bool) $config['lazy'] && strtolower($config['lazy']) !== 'false';
		$this->config = $config;

		if($config['lazy'] === false)
			$this->connect();
	}


	/**
	 * Closes database connection.
	 */
	public function __destruct(){
		try{
			$this->driver->closeConnection();
		} catch(ImplementationException $e){

		}

		$this->notifyObservers(ObserverInterface::DISCONNECT);
	}


	/**
	 * Opens database connection.
	 */
	public function connect(){
		if($this->connected !== false)
			return;

		$this->driver->connect($this->config);
		$this->connected = true;
		$this->notifyObservers(ObserverInterface::CONNECT);
	}


	/**
	 * Returns configuration.
	 * @param string $key
	 * @return mixed
	 */
	public function getConfig($key = null){
		if($key === null)
			return $this->config;
		return isset($this->config[$key]) ? $this->config[$key] : null;
	}


	/**
	 * Returns defined table prefix.
	 * @return string
	 */
	public function getPrefix(){
		return isset($this->config['tablePrefix']) ? $this->config['tablePrefix'] : '';
	}


	/**
	 * Returns the current driver instance.
	 * @return DriverInterface
	 */
	public function getDriver(){
		return $this->driver;
	}


	/**
	 * Returns the current parser class name.
	 * @return string
	 */
	public function getParser(){
		return $this->parser;
	}


	/**
	 * Returns the current cache storage instance.
	 * @return StorageInterface
	 */
	public function getCache(){
		return $this->cache;
	}


	/**
	 * Sets the cache storage.
	 * @param StorageInterface $cache
	 */
	public function setCache(StorageInterface $cache){
		$this->cache = $cache;
	}


	/**
	 * Attaches given observer to given $event.
	 * @param ObserverInterface $observer
	 * @param int $event
	 */
	public function attachObserver(ObserverInterface $observer, $event){
		$this->observers->attach($observer, $event);
	}


	/**
	 * Detaches given observer.
	 * @param ObserverInterface $observer
	 */
	public function detachObserver(ObserverInterface $observer){
		$this->observers->detach($observer);
	}


	/**
	 * Notifies all observers attached to given event.
	 * @param int $event
	 */
	public function notifyObservers($event){
		foreach($this->observers as $observer){
			if($event & $this->observers->getInfo())
				$observer->updateStatus($this, $event);
		}
	}


	/**
	 * Returns configuration value.
	 * @param string $key
	 * @return mixed
	 */
	public function offsetGet($key){
		return $this->getConfig($key);
	}


	/**
	 * Checks if configuration value exists.
	 * @param mixed $key
	 * @return bool
	 */
	public function offsetExists($key){
		return isset($this->config[$key]);
	}


	/** @internal */
	public function offsetSet($offset, $value){

	}


	/** @internal */
	public function offsetUnset($offset){

	}


	/**
	 * Creates an alias for configuration value.
	 * @param array $config Passed by reference
	 * @param string $key
	 * @param string $alias Alias of $key
	 */
	public static function alias(&$config, $key, $alias){
		if(!isset($config[$alias]))
			return;
		$tmp = & $config;
		foreach(explode('.', $key) as $key){
			$tmp = & $tmp[$key];
		}
		if(!isset($tmp))
			$tmp = $config[$alias];
	}


	/**
	 * Sets the driver and statement parser.
	 * @param string $driver
	 * @throws DriverException
	 */
	protected function setDriver($driver){
		if(strcasecmp($driver, 'sqlite') === 0) // Backward compatibility
			$driver = 'SQLite2';

		$class = "Neevo\\Drivers\\{$driver}Driver";

		if(!class_exists($class)){
			$file = __DIR__ . '/Drivers/' . strtolower($driver) . '.php';
			if(!file_exists($file))
				throw new DriverException("$driver driver file ($file) does not exist.");
			if(is_readable($file))
				include_once $file;
			else
				throw new DriverException("$driver driver file ($file) is not readable.");
		}
		if(!$this->isDriver($class))
			throw new DriverException("Class '$class' is not a valid Neevo driver class.");

		$this->driver = new $class;

		// Set statement parser
		if($this->isParser($class))
			$this->parser = $class;
	}


	/**
	 * Checks wether the given class is valid Neevo driver.
	 * @param string $class
	 * @return bool
	 */
	protected function isDriver($class){
		try{
			$reflection = new ReflectionClass($class);
			return $reflection->implementsInterface('Neevo\\DriverInterface');
		} catch(ReflectionException $e){
			return false;
		}
	}


	/**
	 * Checks wether the given class is valid Neevo statement parser.
	 * @param string $class
	 * @return bool
	 */
	protected function isParser($class){
		try{
			$reflection = new ReflectionClass($class);
			return $reflection->isSubclassOf('Neevo\\Parser');
		} catch(ReflectionException $e){
			return false;
		}
	}


}
