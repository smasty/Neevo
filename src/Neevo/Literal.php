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
 * Representation of SQL literal.
 * @author Martin Srank
 * @package Neevo
 */
class Literal {


	/** @var string */
	public $value;


	/**
	 * Create instance of SQL literal.
	 * @param string $value
	 * @return void
	 */
	public function __construct($value){
		$this->value = $value;
	}


}
