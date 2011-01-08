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
 * - table_prefix => prefix for table names
 * - lazy (bool) => If TRUE, connection will be established only when required.
 *
 * @package Neevo
 */
class NeevoConnection {

  private $config, $connected = false;

  /** @var Neevo */
  private $neevo;

  /**
   * Establish a connection.
   * @param array|string|Traversable $config
   * @param Neevo $neevo
   * @throws InvalidArgumentException
   * @return void
   */
  public function __construct($config, Neevo $neevo, $driverName = null){
    $this->neevo = $neevo;

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
      'table_prefix' => ''
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
    self::alias($config, 'table_prefix', 'prefix');
    self::alias($config, 'charset', 'encoding');

    // Backward compatibility
    if(!isset($config['driver']) && $driverName !== null){
      $config['driver'] = $driverName;
    }

    $config += $defaults;

    $this->setDriver($config['driver']);
    $this->config = $config;

    if($config['lazy'] === false){
      $this->realConnect();
    }
  }

  /** @internal */
  public function realConnect(){
    if($this->connected === false){
      $this->driver->connect($this->config);
      $this->connected = true;
    }
  }

  /**
   * Setup driver and statement builder.
   * @param string $driver Driver name
   * @throws NeevoException
   * @return void
   * @internal
   */
  private function setDriver($driver){
    $class = "NeevoDriver$driver";

    if(!$this->isDriver($class)){
      @include_once dirname(__FILE__) . '/drivers/'.strtolower($driver).'.php';

      if(!$this->isDriver($class)){
        throw new NeevoException("Unable to create instance of Neevo driver '$driver' - class not found or not matching criteria.");
      }
    }

    $this->driver = new $class($this->neevo);

    // Set stmtBuilder
    if(in_array('NeevoStmtBuilder', class_parents($class, false))){
      $this->stmtBuilder = $this->driver;
    }
    else{
      $this->stmtBuilder = new NeevoStmtBuilder($this->neevo);
    }
  }

  /** @internal */
  private function isDriver($class){
    return (class_exists($class, false) && in_array('INeevoDriver', class_implements($class, false)));
  }

  /**
   * Get defined table prefix
   * @return string
   */
  public function prefix(){
    return isset($this->config['table_prefix'])
      ? $this->config['table_prefix'] : '';
  }

  /** @return INeevoDriver */
  public function driver(){
    return $this->driver;
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
   * Basic information about the connection.
   * @return array
   */
  public function info(){
    $info = $this->config;
    if(array_key_exists('password', $info)){
      $info['password'] = '*****';
    }
    return $info;
  }

}
