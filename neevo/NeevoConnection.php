<?php
/**
 * Neevo - Tiny open-source database abstraction layer for PHP
 *
 * Copyright 2010-2011 Martin Srank (http://smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * @author   Martin Srank (http://smasty.net)
 * @license  http://neevo.smasty.net/license  MIT license
 * @link     http://neevo.smasty.net/
 *
 */


/**
 * Representation of database connection.
 *
 * Common configuration: (see driver specific configuration too)
 * - tablePrefix => prefix for table names
 * - lazy (bool) => If TRUE, connection will be established only when required.
 * - detectTypes (bool) => Detect column types automatically
 * - formatDateTime => date/time format ("U" for timestamp. If empty, DateTime object used)
 * - rowClass => Name of class to use as a row class.
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

    $this->cache = $cache ? $cache : new NeevoCache;
    
    // Parse
    if(is_string($config)){
      parse_str($config, $config);
    }
    elseif($config instanceof Traversable){
      foreach($config as $key => $val){
        $config[$key] = $val instanceof Traversable ? iterator_to_array($val) : $val;
      }
    }
    elseif(!is_array($config)){
      throw new InvalidArgumentException('Options must be an array, string or Traversable object.');
    }

    // Defaults
    $defaults = array(
      'driver' => Neevo::$defaultDriver,
      'lazy' => false,
      'tablePrefix' => '',
      'formatDateTime' => '',
      'detectTypes' => false,
      'rowClass' => 'NeevoRow'
    );

    // Aliases
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

    $config += $defaults;

    $this->setDriver($config['driver']);
    $this->config = $config;

    if($config['lazy'] === false){
      $this->realConnect();
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
  public function prefix(){
    return isset($this->config['tablePrefix'])
      ? $this->config['tablePrefix'] : '';
  }

  /** @return INeevoDriver */
  public function driver(){
    return $this->driver;
  }

  /** @return NeevoStmtParser */
  public function stmtParser(){
    return $this->stmtParser;
  }

  /** @return INeevoCache */
  public function cache(){
    return $this->cache;
  }

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
      unset($config[$alias]);
    }
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
   * Notify observers.
   * @param int $event
   * @param NeevoStmtBase $statement
   * @return void
   */
  public function notifyObservers($event, NeevoStmtBase $statement = null){
    foreach($this->observers as $observer){
      $observer->updateStatus($this, $event, $statement);
    }
  }


  /*  ************  Internal methods ************  */

  
  /** @internal */
  public function realConnect(){
    if($this->connected === false){
      $this->driver->connect($this->config);
      $this->connected = true;

      $this->notifyObservers(INeevoObserver::CONNECT);
    }
  }

  private function isDriver($class){
    return (class_exists($class) && in_array('INeevoDriver', class_implements($class)));
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
    }
    else{
      $this->stmtParser = new NeevoStmtParser;
    }
  }

}
