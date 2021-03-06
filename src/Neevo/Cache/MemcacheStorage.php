<?php
/**
 * Neevo - Tiny database layer for PHP. (http://neevo.smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * Copyright (c) 2013 Smasty (http://smasty.net)
 *
 */

namespace Neevo\Cache;

use Memcache;


/**
 * Memcache cache storage.
 * @author Smasty
 */
class MemcacheStorage implements StorageInterface {


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
