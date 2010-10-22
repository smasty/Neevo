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


  public function __construct(Neevo $neevo, INeevoDriver $driver, array $options){
    $this->neevo = $neevo;
    $this->driver = $driver;
    $this->options = $options;
    
    $this->driver()->connect($this->options );
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
    if(isset($this->options['table_prefix']))
      return $this->options['table_prefix'];
    return '';
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

}
