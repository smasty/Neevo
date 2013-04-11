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

namespace Neevo;


/**
 * Representation of SQL literal.
 * @author Smasty
 */
class Literal {


	/** @var string */
	public $value;


	/**
	 * Creates instance of SQL literal.
	 * @param string $value
	 */
	public function __construct($value){
		$this->value = $value;
	}


}
