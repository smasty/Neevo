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
 * Neevo cache storage interface.
 * @author Martin Srank
 * @package Neevo\Cache
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
 * Memory cache storage.
 * Default implementation of INeevoCache.
 * @author Martin Srank
 * @package Neevo\Cache
 */
class NeevoCacheMemory implements INeevoCache {


	/** @var array */
	private $data = array();


	public function fetch($key){
		return array_key_exists($key, $this->data) ? $this->data[$key] : null;
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
 * Session cache storage.
 * @author Martin Srank
 * @package Neevo\Cache
 */
class NeevoCacheSession implements INeevoCache {


	public function fetch($key){
		return array_key_exists($key, $_SESSION['NeevoCache']) ? $_SESSION['NeevoCache'][$key] : null;
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
 * File cache storage.
 * @author Martin Srank
 * @package Neevo\Cache
 */
class NeevoCacheFile implements INeevoCache {


	/** @var string */
	private $filename;

	/** @var array */
	private $data = array();


	public function __construct($filename){
		$this->filename = $filename;
		$this->data = (array) unserialize(@file_get_contents($filename));
	}


	public function fetch($key){
		return array_key_exists($key, $this->data) ? $this->data[$key] : null;
	}


	public function store($key, $value){
		if(!array_key_exists($key, $this->data) || $this->data[$key] !== $value){
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
 * Memcache cache storage.
 * @author Martin Srank
 * @package Neevo\Cache
 */
class NeevoCacheMemcache implements INeevoCache {


	/** @var Memcache */
	private $memcache;

	public function __construct(Memcache $memcache){
		$this->memcache = $memcache;
	}


	public function fetch($key){
		$value = $this->memcache->get("NeevoCache.$key");
		return $value !== false ? $value : null;
	}


	public function store($key, $value){
		$this->memcache->set("NeevoCache.$key", $value);
	}


	public function flush(){
		return $this->memcache->flush();
	}


}
