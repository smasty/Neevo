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

namespace Neevo\Cache;


/**
 * Memory cache storage.
 * Default implementation of Neevo\ICache.
 * @author Smasty
 */
class MemoryStorage implements StorageInterface {


	/** @var array */
	private $data = array();


	public function fetch($key){
		return array_key_exists($key, $this->data) ? $this->data[$key] : null;
	}


	public function store($key, $value){
		$this->data[$key] = $value;
	}


	public function flush(){
		return!$this->data = array();
	}


}

