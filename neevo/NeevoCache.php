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
 * @link     http://neevo.smasty.net
 * @package  Neevo
 *
 */

/**
 * Interface for NeevoCache classes.
 * @package NeevoCache
 */
interface INeevoCache {


  /**
   * Load stored data
   * @param string $key
   * @return mixed|null null if not found
   */
  public function load($key);
  

  /**
   * Save data
   * @param string $key
   * @param mixed $value
   * @return void
   */
  public function save($key, $value);

}


/**
 * Neevo session cache
 * @package NeevoCache
 */
class NeevoCacheSession implements INeevoCache {


  public function load($key){
    if(!isset($_SESSION['NeevoCache'][$key]))
      return null;
    return $_SESSION['NeevoCache'][$key];
  }


  public function save($key, $value){
    $_SESSION['NeevoCache'][$key] = $value;
  }

}


/**
 * Neevo file cache
 * @package NeevoCache
 */
class NeevoCacheFile implements INeevoCache {

  /** @var string */
  private $filename;

  /** @var array */
  private $data = array();

  public function __construct($filename){
    $this->filename = $filename;
    $this->data = unserialize(@file_get_contents($filename));
  }


  public function __destruct(){
    @file_put_contents($this->filename, serialize($this->data), LOCK_EX);
  }


  public function load($key){
    if(!isset($this->data[$key]))
      return null;
    return $this->data[$key];
  }


  public function save($key, $value){
    $this->data[$key] = $value;
  }

}


/**
 * Neevo Memcache cache
 * @package NeevoCache
 */
/*class NeevoCacheMemcache implements INeevoCache {

  /** @var Memcache *//*
  private $memcache;

  public function __construct(Memcache $memcache){
    $this->memcache = $memcache;
  }

  public function load($key){
    $value = $this->memcache->get("NeevoCache.$key");
    if($value === false)
      return null;
    return $value;
  }


  public function save($key, $value){
    $this->memcache->set("NeevoCache.$key", $value);
  }

}*/


/**
 * Neevo APC cache
 * @package NeevoCache
 */
class NeevoCacheAPC implements INeevoCache {


  public function load($key){
    $value = apc_fetch("NeevoCache.$key", $success);
    if(!$success)
      return null;
    return $value;
  }


  public function save($key, $value){
    apc_store("NeevoCache.$key", $value);
  }

}
?>
