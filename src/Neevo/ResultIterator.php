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

namespace Neevo;

use Countable;
use Iterator;
use OutOfRangeException;
use SeekableIterator;


/**
 * Result set iterator.
 * @author Smasty
 */
class ResultIterator implements Iterator, Countable, SeekableIterator {


	/** @var int */
	private $pointer;

	/** @var Result */
	private $result;

	/** @var Row */
	private $row;


	public function __construct(Result $result){
		$this->result = $result;
	}


	/**
	 * Rewinds the iterator.
	 * Force execution for future iterations.
	 */
	public function rewind(){
		if($this->row !== null)
			$this->result = clone $this->result;
		$this->pointer = 0;
		$this->row = $this->result->fetch();
	}


	/**
	 * Moves to next row.
	 */
	public function next(){
		$this->row = $this->result->fetch();
		$this->pointer++;
	}


	/**
	 * Checks for valid current row.
	 * @return bool
	 */
	public function valid(){
		return $this->row !== false;
	}


	/**
	 * Returns the current row.
	 * @return Row
	 */
	public function current(){
		return $this->row;
	}


	/**
	 * Returns the key of current row.
	 * @return int
	 */
	public function key(){
		return $this->pointer;
	}


	/**
	 * Implementation of Countable.
	 * @return int
	 * @throws DriverException on unbuffered result.
	 */
	public function count(){
		return $this->result->count();
	}


	/**
	 * Implementation of SeekableIterator.
	 * @param int $offset
	 * @throws OutOfRangeException|DriverException
	 */
	public function seek($offset){
		try{
			$this->result->seek($offset);
		} catch(DriverException $e){
			throw $e;
		} catch(NeevoException $e){
			throw new OutOfRangeException("Cannot seek to offset $offset.", null, $e);
		}
		$this->row = $this->result->fetch();
		$this->pointer = $offset;
	}


}
