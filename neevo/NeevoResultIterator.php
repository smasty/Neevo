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
class NeevoResultIterator implements Iterator, Countable, SeekableIterator, ArrayAccess {

  /** @var NeevoResult */
  private $result;

  /** @var int */
  private $position;

  /** @var array */
  private $data;

  public function __construct(NeevoResult $result){
    $this->result = $result;
    if($this->result->isPerformed())
      $this->data = $this->result->getData();
  }
  
  
  /**
   * Rewinds the result iterator
   * @return void
   */
  public function rewind(){
    if(!empty($this->data)) // Force execution for future loops
      $this->result->reinit();
    $this->data = $this->result->fetch();
    $this->position = 0;
  }
  
  
  /**
   * The key of current element
   * @return int
   */
  public function key(){
    return $this->position;
  }


  /**
   * Moves to next element
   * @return void
   */
  public function next(){
    $this->position++;
  }
  
  
  /**
   * The current element
   * @return NeevoRow
   */
  public function current(){
    return $this->data[$this->position];
  }
  
  
  /**
   * Checks if there's a valid element
   * @return bool
   */
  public function valid(){
    return (!empty($this->data[$this->position]));
  }
  
  
  /**
   * Implementation of Countable
   * @return int
   */
  public function count(){
    return count($this->data);
  }


  /**
   * Implementation of SeekableIterator
   * @return int
   */
  public function seek($position){
    $this->position = $position;
  }


  /* Implementation of Array Access */

  public function offsetSet($offset, $value){
    if(isset($this->data[$offset]))
      $this->data[$offset] = $value;
  }


  public function offsetExists($offset){
    return isset($this->data[$offset]);
  }


  public function offsetUnset($offset){
    unset($this->data[$offset]);
  }


  public function offsetGet($offset){
    return isset($this->data[$offset]) ? $this->data[$offset] : null;
  }


}
