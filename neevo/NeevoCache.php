<?php
/**
 * Neevo - Tiny database layer for PHP. (http://neevo.smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * Copyright (c) 2011 Martin Srank (http://smasty.net)
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


	/** @var array */
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


	/** @var string */
	private $filename;

	/** @var array */
	private $data = array();


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
 * Neevo cache using `NeevoCache.` prefix in Memcache.
 * @author Martin Srank
 * @package NeevoCache
 */
class NeevoCacheMemcache implements INeevoCache {


	/** @var Memcache */
	private $memcache;

	/** @var array */
	private $keys = array();


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
 * Neevo cache using Nette Framework cache.
 * @author Martin Srank
 * @package NeevoCache
 */
class NeevoCacheNette implements INeevoCache {


	/** @var Nette\Caching\Cache */
	private $cache;


	public function __construct(){
		if(!(defined('NETTE_VERSION_ID') && NETTE_VERSION_ID  < 20000)){
			throw new NeevoException('Could not detect Nette Framework 2.0 or greater.');
		}

		// @nette Nette Framework compatiblility
		if(is_callable('Nette\Environment::getCache')){
			$cache = call_user_func('Nette\Environment::getCache');
			$c = 'Nette\Caching\Cache';
		} elseif(is_callable('NEnvironment::getCache')){
			$cache = NEnvironment::getCache();
			$c = 'NCache';
		} elseif(is_callable('Environment::getCache')){
			$cache = Environment::getCache();
			$c = 'Cache';
		} else{
			throw new NeevoException('Could not detect Nette Framework cache.');
		}

		$this->cache = new $c($cache->getStorage(), 'Neevo.Cache');
	}


	public function fetch($key){
		return $this->cache[$key];
	}


	public function store($key, $value){
		$this->cache->save($key, $value);
	}


	public function flush(){
		$this->cache->clean();
		return true;
	}


}
