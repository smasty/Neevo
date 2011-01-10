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
 * @license  http://neevo.smasty.net/license  MIT license
 * @link     http://neevo.smasty.net/
 *
 */

/**
 * Result set iterator.
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
   *
   * Force execution for future iterations.
   * @return void
   */
  public function rewind(){
    if($this->row){
      $this->result->reinit();
    }

    $this->pointer = 0;
    $this->row = $this->result->fetch();
  }

  /**
   * Move to next row.
   * @return void
   */
  public function next(){
    $this->pointer++;
    $this->row = $this->result->fetch();
  }

  /**
   * Check for valid current row.
   * @return bool
   */
  public function valid(){
    return $this->row !== false;
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
   * @throws NeevoDriverException on unbuffered result.
   * @return int
   */
  public function count(){
    return $this->result->rows();
  }

  /**
   * Implementation of SeekableIterator
   * @throws NeevoDriverException on unbuffered result.
   * @param int $offset
   */
  public function seek($offset){
    $this->result->seek($offset);
  }

}
