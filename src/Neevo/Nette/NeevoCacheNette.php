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
use Nette\Caching\IStorage,
	Nette\Caching\Cache;


/**
 * Neevo cache adapter for Nette Framework cache storage system.
 * @author Martin Srank
 * @package NeevoCache
 */
class NeevoCacheNette implements INeevoCache {


	/** @var string */
	public static $cacheKey = 'Neevo.Cache';

	/** @var Cache */
	private $cache;


	/**
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
