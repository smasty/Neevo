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
 * File cache storage.
 * @author Smasty
 */
class FileStorage implements CacheInterface {


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

