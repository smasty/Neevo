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

namespace Neevo;


/**
 * Neevo cache storage interface.
 * @author Martin Srank
 */
interface Cache {


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
