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

}


/**
 * Neevo cache using `$_SESSION['NeevoCache']`.
 * @package NeevoCache
 */
class NeevoCacheSession implements INeevoCache {

  public function fetch($key){
    return isset($_SESSION['NeevoCache'][$key]) ? $_SESSION['NeevoCache'][$key] : null;
  }

  public function store($key, $value){
    $_SESSION['NeevoCache'][$key] = $value;
  }

}


/**
 * Neevo cache using file.
 * @package NeevoCache
 */
class NeevoCacheFile implements INeevoCache {

  private $filename;

  private $data = array();

  public function __construct($filename){
    $this->filename = $filename;
    $this->data = unserialize(@file_get_contents($filename));
  }

  public function fetch($key){
    if(!isset($this->data[$key])){
      return null;
    }
    return $this->data[$key];
  }

  public function store($key, $value){
    if(!isset($this->data[$key]) || $this->data[$key] !== $value){
      $this->data[$key] = $value;
      file_put_contents($this->filename, serialize($this->data), LOCK_EX);
    }
  }

}


/**
 * Neevo cache using PHP included file.
 */
class NeevoCacheInclude implements INeevoCache {

  private $filename;

  private $data = array();

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
			file_put_contents($this->filename, '<?php return '.var_export($this->data, true).';', LOCK_EX);
		}
	}

}


/**
 * Neevo cache using database table `neevo_cache`.
 *
 * The table must already exist:
 *
 * <pre>CREATE TABLE neevo_cache (
 *   id varchar(255) NOT NULL,
 *   data text NOT NULL,
 *   PRIMARY KEY (id)
 * );</pre>
 */
/*class NeevoCacheDatabase implements INeevoCache {

  private $driver;

  public function __construct(NeevoConnection $connection){
    $this->driver = $connection->driver();
  }

  public function fetch($key){
    try{
      $q = $this->driver->query("SELECT data FROM neevo_cache WHERE id = "
       . $this->driver->escape($key, Neevo::TEXT));
      $row = $this->driver->fetch($q);
      if($row !== false){
        return unserialize($row['data']);
      }
      else{
        return null;
      }
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

}*/


/**
 * Neevo cache using `NeevoCache.` prefix in Memcache.
 * @package NeevoCache
 */
class NeevoCacheMemcache implements INeevoCache {

  private $memcache;

  public function __construct(Memcache $memcache){
    $this->memcache = $memcache;
  }

  public function fetch($key){
    $value = $this->memcache->fetch("NeevoCache.$key");
    if($value === false){
      return null;
    }
    return $value;
  }

  public function store($key, $value){
    $this->memcache->set("NeevoCache.$key", $value);
  }

}


/**
 * Neevo cache using `NeevoCache.` prefix in APC.
 * @package NeevoCache
 */
class NeevoCacheAPC implements INeevoCache {

  public function fetch($key){
    $value = apc_fetch("NeevoCache.$key", $success);
    if(!$success){
      return null;
    }
    return $value;
  }

  public function store($key, $value){
    apc_store("NeevoCache.$key", $value);
  }

}
?>
