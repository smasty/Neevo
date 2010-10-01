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
 * Neevo connection class
 * @package Neevo
 */
class NeevoConnection{

  private $neevo, $driver, $username, $password, $host, $database, $encoding, $table_prefix, $resource;


  public function __construct(Neevo $neevo, INeevoDriver $driver, $user, $pswd = null, $host, $database, $encoding = null, $table_prefix = null){
    $this->neevo = $neevo;
    $this->driver = $driver;
    $this->username = $user;
    $this->password = $pswd;
    $this->host = $host;
    $this->database = $database;
    $this->encoding = $encoding;
    $this->table_prefix = $table_prefix;

    $resource = $this->driver()->connect($this->getVars());
    $this->setResource($resource);
  }


  /**
   * Returns current NeevoDriver
   * @return INeevoDriver
   */
  private function driver(){
    return $this->driver;
  }


  /**
   * Returns object variables as associative array
   * @return array
   */
  public function getVars(){
    $options = get_object_vars($this);
    unset($options['neevo'], $options['driver'], $options['resource']);
    return $options;
  }


  public function prefix(){
    return $this->table_prefix;
  }


  /**
   * Sets connection resource
   * @param resource $resource
   * @return void
   */
  public function setResource($resource){
    if(is_resource($resource))
      $this->resource = $resource;
  }


  /**
   * Returns resource identifier
   * @return resource
   */
  public function resource(){
    return $this->resource;
  }


  /**
   * Returns basic informations about current connection
   * @param bool $hide_password If set to TRUE (default), password will be replaced by '*****'.
   * @return array
   */
  public function info($hide_password = true){
    $info = $this->getVars();
    if($hide_password) $info['password'] = '*****';
    $info['driver'] = str_replace('NeevoDriver', '', get_class($this->driver));
    return $info;
  }

}
