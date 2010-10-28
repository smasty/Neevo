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
 * Can be created by calling Neevo->createConnection().
 * @package Neevo
 */
class NeevoConnection{
  
  /** @var INeevoDriver */
  private $driver;

  /** @var array */
  private $config;


  public function __construct(INeevoDriver $driver, $config){
    $this->driver = $driver;

    if(is_string($config))
      parse_str($config, $config);
    elseif($config instanceof Traversable){
      $tmp = array();
      foreach($config as $key=>$val)
        $tmp[$key] = $val instanceof Traversable ? iterator_to_array($val) : $val;
      $config = $tmp;
    }
    elseif(!is_array($config))
      throw new InvalidArgumentException('Options must be array, string or object.');

    self::alias($config, 'username', 'user');
    self::alias($config, 'password', 'pass');
    self::alias($config, 'password', 'pswd');
    self::alias($config, 'host', 'hostname');
    self::alias($config, 'host', 'server');
    self::alias($config, 'database', 'db');
    self::alias($config, 'database', 'dbname');
    self::alias($config, 'table_prefix', 'prefix');

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
   * @param array $opts
   * @param string $key
   * @param string $alias Alias of $key
   * @return void
   */
  public static function alias(&$opts, $key, $alias){
    if(isset($opts[$alias]) && !isset($opts[$key]))
      $opts[$key] = $opts[$alias];
  }

}
