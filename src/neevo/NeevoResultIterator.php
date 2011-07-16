<?php
/**
 * Neevo - Tiny open-source database abstraction layer for PHP
 *
 * Copyright 2010-2011 Martin Srank (http://smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * @author   Martin Srank (http://smasty.net)
 * @license  http://neevo.smasty.net/license MIT license
 * @link      http://neevo.smasty.net/
 *
 */


/**
 * Result set iterator.
 * @author Martin Srank
 * @package Neevo
 */
class NeevoResultIterator implements Iterator, Countable, SeekableIterator {


	/** @var int */
	private $pointer;

	/** @var NeevoResult */
	private $result;

	/** @var NeevoRow */
	private $row;


	public function __construct(NeevoResult $result){
		$this->result = $result;
	}


	/**
	 * Rewind the iterator.
	 * Force execution for future iterations.
	 * @return void
	 */
	public function rewind(){
		if($this->row !== null)
			$this->result = clone $this->result;
		$this->pointer = 0;
	}


	/**
	 * Move to next row.
	 * @return void
	 */
	public function next(){
		++$this->pointer;
	}


	/**
	 * Check for valid current row.
	 * @return bool
	 */
	public function valid(){
		return ($this->row = $this->result->fetch()) !== false;
	}


	/**
	 * Return the current row.
	 * @return NeevoRow
	 */
	public function current(){
		return $this->row;
	}


	/**
	 * Return the key of current row.
	 * @return int
	 */
	public function key(){
		return $this->pointer;
	}


	/**
	 * Implementation of Countable.
	 * @return int
	 * @throws NeevoDriverException on unbuffered result.
	 */
	public function count(){
		return $this->result->count();
	}


	/**
	 * Implementation of SeekableIterator.
	 * @param int $offset
	 * @throws OutOfRangeException|NeevoDriverException
	 */
	public function seek($offset){
		try{
			$this->result->seek($offset);
		} catch(NeevoDriverException $e){
			throw $e;
		} catch(NeevoException $e){
			throw new OutOfRangeException("Cannot seek to offset $offset.", null, $e);
		}
		$this->pointer = $offset;
	}


}
