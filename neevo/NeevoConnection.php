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
 * @license  http://www.opensource.org/licenses/mit-license.php  MIT license
 * @link     http://neevo.smasty.net/
 * @package  Neevo
 *
 */

/**
 * Internal class to represent connection to database server.
 * Can be created by calling Neevo->createConnection().
 * @package Neevo
 */
class NeevoConnection{

  private $neevo, $driver, $options;


  public function __construct(Neevo $neevo, INeevoDriver $driver, $options){
    $this->neevo = $neevo;
    $this->driver = $driver;

    if(is_string($options))
      parse_str($options, $options);
    elseif($options instanceof Traversable){
      $tmp = array();
      foreach($options as $key=>$val)
        $tmp[$key] = $val instanceof Traversable ? iterator_to_array($val) : $val;
      $options = $tmp;
    }
    elseif(!is_array($options))
      throw new InvalidArgumentException('Options must be array, string or object.');

    self::alias($options, 'username', 'user');
    self::alias($options, 'password', 'pass');
    self::alias($options, 'password', 'pswd');
    self::alias($options, 'host', 'hostname');
    self::alias($options, 'database', 'db');
    self::alias($options, 'database', 'dbname');

    $this->options = $options;
    
    $this->driver()->connect($this->options);
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
    return $this->options;
  }


  public function prefix(){
    return isset($this->options['table_prefix']) ? $this->options['table_prefix'] : '';
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
