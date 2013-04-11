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


/**
 * Neevo cache storage interface.
 * @author Smasty
 */
interface StorageInterface {


	/**
	 * Fetches stored data.
	 * @param string $key
	 * @return mixed|null null if not found
	 */
	public function fetch($key);


	/**
	 * Stores data in cache.
	 * @param string $key
	 * @param mixed $value
	 */
	public function store($key, $value);


}
