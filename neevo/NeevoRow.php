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
 * Representation of a row in a result.
 * @package Neevo
 */
class NeevoRow implements ArrayAccess, Countable, IteratorAggregate {

  private $freeze, $primaryKey, $table, $singleValue, $single = false;
  private $data = array(), $modified = array(), $iterable = array();

  /** @var Neevo */
  private $neevo;


  /**
   * Create a row instance.
   * @param array $data
   * @param NeevoResult $result
   * @return void
   */
  public function __construct(array $data, NeevoResult $result){
    $this->data = $data;
    $this->iterable = $this->data;
    $this->neevo = $result->neevo();
    $this->primaryKey = $result->getPrimaryKey();
    $this->table = $result->getTable();

    if(!isset($this->data[$this->primaryKey])){
      $this->freeze = true;
    }

    if(count($data) === 1){
      $this->single = true;
      $this->singleValue = reset($this->data);
      $this->freeze = true;
    }
  }


  /**
   * Update corresponding database row if available.
   * @throws NeevoException
   * @return int Number of affected rows.
   */
  public function update(){
    if(!empty($this->modified) && $this->data != $this->iterable && !$this->freeze){
      return $this->neevo->update($this->table, $this->modified)
        ->where($this->primaryKey, $this->data[$this->primaryKey])
        ->limit(1)->affectedRows();
    }
    throw new NeevoException('Update disabled - cannot get primary key.');
  }


  /**
   * Delete corresponding database row if available.
   * @throws NeevoException
   * @return int Number of affected rows.
   */
  public function delete(){
    if(!$this->freeze){
      return $this->neevo->delete($this->table)
        ->where($this->primaryKey, $this->data[$this->primaryKey])
        ->limit(1)->affectedRows();
    }
    throw new NeevoException('Delete disabled - cannot get primary key.');
  }


  /** @internal */
  public function __toString(){
    if($this->single === true){
      return (string) $this->singleValue;
    }
    return '';
  }


  /**
   * Is there only one value in row?
   * @return bool
   */
  public function isSingle(){
    return $this->single;
  }


  /**
   * If there is only one value in row, return it.
   * @return mixed|void
   */
  public function getSingle(){
    if($this->isSingle()){
      return $this->singleValue;
    }
  }


  /**
   * Return object as an array.
   * @return array
   */
  public function toArray(){
    return $this->iterable;
  }


  /** @internal */
  public function __get($name){
    if($this->single){
      return $this->singleValue;
    }
    return isset($this->modified[$name]) ? $this->modified[$name] :
      isset($this->data[$name]) ? $this->data[$name] : null;
  }


  /** @internal */
  public function __set($name, $value){
    if(isset($this->data[$name])){
      $this->modified[$name] = $value;
      $this->iterable = array_merge($this->data, $this->modified);
    }
  }


  /** @internal */
  public function __isset($name){
    return isset($this->data[$name]);
  }


  /** @internal */
  public function __unset($name){
    $this->modified[$offset] = null;
    $this->iterable = array_merge($this->data, $this->modified);
  }


  /* Implementation of Array Access */

  /** @internal */
  public function offsetGet($offset){
    return $this->__get($offset);
  }


  /** @internal */
  public function offsetSet($offset, $value){
    $this->__set($offset, $value);
  }


  /** @internal */
  public function offsetExists($offset){
    return $this->__isset($offset);
  }


  /** @internal */
  public function offsetUnset($offset){
    $this->__unset($offset);
  }


  /* Implementation of Countable */

  public function count(){
    return count($this->iterable);
  }


  /* Implementation of IteratorAggregate */

  public function getIterator(){
    return new ArrayIterator($this->iterable);
  }

}