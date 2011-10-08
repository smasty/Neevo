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

namespace Neevo\Nette;


use Neevo,
	Nette;


/**
 * Cache adapter for Nette Framework cache storage system.
 * @author Martin Srank
 */
class Cache implements Neevo\ICache {


	/** @var string */
	public static $cacheKey = 'Neevo.Cache';

	/** @var Nette\Caching\Cache */
	private $cache;


	/**
	 * Create the cache adapter.
	 * @param Nette\Caching\IStorage $storage
	 */
	public function __construct(Nette\Caching\IStorage $storage){
		$this->cache = new Nette\Caching\Cache($storage, self::$cacheKey);
	}


	public function fetch($key){
		return $this->cache[$key];
	}


	public function store($key, $value){
		$this->cache[$key] = $value;
	}


	public function flush(){
		$this->cache->clean();
		return true;
	}


}
