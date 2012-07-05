<?php
/**
 * Neevo - Tiny database layer for PHP. (http://neevo.smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * Copyright (c) 2012 Smasty (http://smasty.net)
 *
 */

namespace Neevo\Nette;

use Neevo\Cache\CacheInterface;
use Nette\Caching\Cache;
use Nette\Caching\IStorage;


/**
 * Cache adapter for Nette Framework cache storage system.
 * @author Smasty
 */
class CacheAdapter implements CacheInterface {


	/** @var string */
	public static $cacheKey = 'Neevo.Cache';

	/** @var Cache */
	private $cache;


	/**
	 * Creates the cache adapter.
	 * @param IStorage $storage
	 */
	public function __construct(IStorage $storage){
		$this->cache = new Cache($storage, self::$cacheKey);
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
