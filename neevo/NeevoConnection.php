<?php
/**
 * Neevo - Tiny open-source database abstraction layer for PHP
 *
 * Copyright 2010 Martin Srank (http://smasty.net)
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
 * Internal class to represent connection to database server.
 * @package Neevo
 */
class NeevoConnection{
  
  /** @var INeevoDriver */
  private $driver;

  /** @var array */
  private $config;


  /**
   * Instantiate connection
   * @param INeevoDriver $driver
   * @param array|string|Traversable $config
   * @throws InvalidArgumentException
   * @return void
   */
  public function __construct(INeevoDriver $driver, $config){
    $this->driver = $driver;

    if(is_string($config))
      parse_str($config, $config);
    elseif($config instanceof Traversable){
      foreach($config as $key=>$val)
        $config[$key] = $val instanceof Traversable ? iterator_to_array($val) : $val;
    }
    elseif(!is_array($config))
      throw new InvalidArgumentException('Options must be an array, string or Traversable object.');

    self::alias($config, 'username', 'user');
    self::alias($config, 'password', 'pass');
    self::alias($config, 'password', 'pswd');
    self::alias($config, 'host', 'hostname');
    self::alias($config, 'host', 'server');
    self::alias($config, 'database', 'db');
    self::alias($config, 'database', 'dbname');
    self::alias($config, 'table_prefix', 'prefix');
    self::alias($config, 'encoding', 'charset');

    $this->config = $config;
    
    $this->driver()->connect($this->config);
  }


  /**
   * Current NeevoDriver
   * @return INeevoDriver
   */
  private function driver(){
    return $this->driver;
  }


  /**
   * Object variables as associative array
   * @return array
   */
  public function getVars(){
    return $this->config;
  }


  public function prefix(){
    return isset($this->config['table_prefix']) ? $this->config['table_prefix'] : '';
  }


  /**
   * Basic information about current connection
   * @param bool $hide_password Password will be replaced by '*****'.
   * @return array
   */
  public function info($hide_password = true){
    $info = $this->getVars();
    if($hide_password) $info['password'] = '*****';
    $info['driver'] = str_replace('NeevoDriver', '', get_class($this->driver));
    return $info;
  }


  /**
   * Create alias for configuration value
   * @param array $config
   * @param string $key
   * @param string $alias Alias of $key
   * @return void
   */
  public static function alias(&$config, $key, $alias){
    if(isset($config[$alias]) && !isset($config[$key]))
      $config[$key] = $config[$alias];
  }

}
