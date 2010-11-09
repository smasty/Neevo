<?php
/**
 * Neevo - Tiny open-source database abstraction layer for PHP
 *
 * Copyright 2010 Martin Srank (http://smasty.net)
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
 * NeevoResult Iterator
 * @package Neevo
 */
class NeevoResultIterator implements Iterator, Countable {

  /** @var NeevoResult */
  private $result;

  /** @var array */
  private $keys;

  /** @var array */
  private $data;

  public function __construct(NeevoResult $result){
    $this->result = $result;
  }
  
  
  /**
   * Rewinds the result iterator
   * @return void
   */
  public function rewind(){
    if(!empty($this->keys)) // Force execution for future loops
      $this->result->reinit();
    $this->data = $this->result->fetch();
    $this->keys = array_keys($this->data);
		reset($this->keys);
  }
  
  
  /**
   * The key of current element
   * @return int
   */
  public function key(){
    return current($this->keys);
  }


  /**
   * Moves to next element
   * @return void
   */
  public function next(){
    next($this->keys);
  }
  
  
  /**
   * The current element
   * @return NeevoRow
   */
  public function current(){
    return $this->data[current($this->keys)];
  }
  
  
  /**
   * Checks if there's a valid element
   * @return bool
   */
  public function valid(){
    return current($this->keys) !== false;
  }
  
  
  /**
   * Implementation of Countable
   * @return int
   */
  public function count(){
    return count($this->data);
  }

}
