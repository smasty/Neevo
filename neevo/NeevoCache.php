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
 * Interface for NeevoCache classes.
 * @author Martin Srank
 * @package NeevoCache
 */
interface INeevoCache {

  /**
   * Fetch stored data.
   * @param string $key
   * @return mixed|null null if not found
   */
  public function fetch($key);

  /**
   * Store data in cache.
   * @param string $key
   * @param mixed $value
   * @return void
   */
  public function store($key, $value);

  /**
   * Flush entire cache.
   * @return bool
   */
  public function flush();

}



/**
 * Default implementation of INeevoCache.
 * @author Martin Srank
 * @package NeevoCache
 */
class NeevoCache implements INeevoCache {

  private $data = array();

  public function fetch($key){
    return isset($this->data[$key]) ? $this->data[$key] : null;
  }

  public function store($key, $value){
    $this->data[$key] = $value;
  }

  public function flush(){
    $this->data = array();
    return empty($this->data);
  }

}



/**
 * Neevo cache using `$_SESSION['NeevoCache']`.
 * @author Martin Srank
 * @package NeevoCache
 */
class NeevoCacheSession implements INeevoCache {

  public function fetch($key){
    return isset($_SESSION['NeevoCache'][$key]) ? $_SESSION['NeevoCache'][$key] : null;
  }

  public function store($key, $value){
    $_SESSION['NeevoCache'][$key] = $value;
  }

  public function flush(){
    $_SESSION['NeevoCache'] = array();
    return empty($_SESSION['NeevoCache']);
  }

}



/**
 * Neevo cache using file.
 * @author Martin Srank
 * @package NeevoCache
 */
class NeevoCacheFile implements INeevoCache {

  private $filename, $data = array();

  public function __construct($filename){
    $this->filename = $filename;
    $this->data = unserialize(@file_get_contents($filename));
  }

  public function fetch($key){
    return isset($this->data[$key]) ? $this->data[$key] : null;
  }

  public function store($key, $value){
    if(!isset($this->data[$key]) || $this->data[$key] !== $value){
      $this->data[$key] = $value;
      @file_put_contents($this->filename, serialize($this->data), LOCK_EX);
    }
  }

  public function flush(){
    $this->data = array();
    return @file_put_contents($this->filename, serialize($this->data), LOCK_EX);
  }

}



/**
 * Neevo cache using PHP included file.
 * @author Martin Srank
 * @package NeevoCache
 */
class NeevoCacheInclude implements INeevoCache {

  private $filename, $data = array();

  public function __construct($filename){
		$this->filename = $filename;
		$this->data = @include realpath($filename);
		if(!is_array($this->data)){
			$this->data = array();
		}
	}

	public function fetch($key){
		return isset($this->data[$key]) ? $this->data[$key] : null;
	}

	public function store($key, $value) {
		if(!isset($this->data[$key]) || $this->data[$key] !== $value){
			$this->data[$key] = $value;
			@file_put_contents($this->filename, '<?php return '.var_export($this->data, true).';', LOCK_EX);
		}
	}

  public function flush(){
    $this->data = array();
	  return @file_put_contents($this->filename, '<?php return '.var_export($this->data, true).';', LOCK_EX);
  }

}



/**
 * Neevo cache using database table 'neevo_cache'.
 *
 * The table must already exist:
 * <code>
 * CREATE TABLE neevo_cache (
 *   id varchar(255) NOT NULL,
 *   data text NOT NULL,
 *   PRIMARY KEY (id)
 * );</code>
 * @author Martin Srank
 * @package NeevoCache
 */
class NeevoCacheDB implements INeevoCache {

  /** @var INeevoDriver */
  private $driver;

  public function __construct(NeevoConnection $connection){
    $this->driver = $connection->driver();
    $connection->realConnect();
  }

  public function fetch($key){
    try{
      $q = $this->driver->query("SELECT data FROM neevo_cache WHERE id = "
       . $this->driver->escape($key, Neevo::TEXT));
      $row = $this->driver->fetch($q);
      return $row !== false ? unserialize($row['data']) : null;
    } catch(Exception $e){
        return null;
    }
  }

  public function store($key, $value){
    $data = array(
      'id' => $this->driver->escape($key, Neevo::TEXT),
      'data' => $this->driver->escape(serialize($value), Neevo::TEXT)
    );
    $q = $this->driver->query("UPDATE neevo_cache SET data = $data[data] WHERE id = $data[id]");
    if(!$this->driver->affectedRows()){
      try{
        $this->driver->query("INSERT INTO neevo_cache (id, data) VALUES ($data[id], $data[data])");
      } catch(Exception $e){}
    }
  }

  public function flush(){
    try{
     return (bool) $this->driver->query("DELETE FROM neevo_cache");
    } catch(Exception $e){
        return false;
      }
  }

}



/**
 * Neevo cache using `NeevoCache.` prefix in Memcache.
 * @author Martin Srank
 * @package NeevoCache
 */
class NeevoCacheMemcache implements INeevoCache {

  private $memcache, $keys = array();

  public function __construct(Memcache $memcache){
    $this->memcache = $memcache;
  }

  public function fetch($key){
    $value = $this->memcache->fetch("NeevoCache.$key");
    return $value !== false ? $value : null;
  }

  public function store($key, $value){
    $this->memcache->set("NeevoCache.$key", $value);
    $this->keys[] = $key;
  }

  public function flush(){
    foreach($this->keys as $key){
      $this->memcache->delete($key);
    }
    $this->keys = array();
    return true;
  }

}



/**
 * Neevo cache using `NeevoCache.` prefix in APC.
 * @author Martin Srank
 * @package NeevoCache
 */
class NeevoCacheAPC implements INeevoCache {

  private $keys = array();

  public function fetch($key){
    $value = apc_fetch("NeevoCache.$key", $success);
    if(!$success){
      return null;
    }
    return $value !== false ? $value : null;
  }

  public function store($key, $value){
    apc_store("NeevoCache.$key", $value);
    $this->keys[] = $key;
  }

  public function flush(){
    foreach($this->keys as $key){
      apc_delete($key);
    }
    $this->keys = array();
    return true;
  }

}
