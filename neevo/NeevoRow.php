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
 * @author Martin Srank
 * @package Neevo
 */
class NeevoRow implements ArrayAccess, Countable, IteratorAggregate {

  private $freeze, $primaryKey, $table;
  private $data = array(), $modified = array(), $iterable = array();

  /** @var NeevoConnection */
  private $connection;

  /**
   * Create a row instance.
   * @param array $data
   * @param NeevoResult $result
   * @return void
   */
  public function __construct(array $data, NeevoResult $result){
    $this->data = $data;
    $this->iterable = $this->data;
    $this->connection = $result->connection();
    $this->primaryKey = $result->getPrimaryKey();
    $this->table = $result->getTable();

    if(!isset($this->data[$this->primaryKey])){
      $this->freeze = true;
    }
  }

  /**
   * Update corresponding database row if available.
   * @throws NeevoException
   * @return int Number of affected rows.
   */
  public function update(){
    if($this->freeze){
     throw new NeevoException('Update disabled - cannot get primary key.');
    }
    if(!empty($this->modified) && $this->data != $this->iterable){
      $stmt = new NeevoStmt($this->connection);
      return $stmt->update($this->table, $this->modified)
        ->where($this->primaryKey, $this->data[$this->primaryKey])
        ->limit(1)->affectedRows();
    }
  }

  /**
   * Delete corresponding database row if available.
   * @throws NeevoException
   * @return int Number of affected rows.
   */
  public function delete(){
    if(!$this->freeze){
      $stmt = new NeevoStmt($this->connection);
      return $stmt->delete($this->table)
        ->where($this->primaryKey, $this->data[$this->primaryKey])
        ->limit(1)->affectedRows();
    }
    throw new NeevoException('Delete disabled - cannot get primary key.');
  }

  /**
   * Return object as an array.
   * @return array
   */
  public function toArray(){
    return $this->iterable;
  }

  public function __get($name){
    return isset($this->modified[$name]) ? $this->modified[$name] :
      isset($this->data[$name]) ? $this->data[$name] : null;
  }

  public function __set($name, $value){
      $this->modified[$name] = $value;
      $this->iterable = array_merge($this->data, $this->modified);
  }

  public function __isset($name){
    return isset($this->data[$name]);
  }

  public function __unset($name){
    $this->modified[$offset] = null;
    $this->iterable = array_merge($this->data, $this->modified);
  }


  /*  ************  Implementation of Array Access  ************  */


  public function offsetGet($offset){
    return $this->__get($offset);
  }

  public function offsetSet($offset, $value){
    $this->__set($offset, $value);
  }

  public function offsetExists($offset){
    return $this->__isset($offset);
  }

  public function offsetUnset($offset){
    $this->__unset($offset);
  }


  /*  ************  Implementation of Countable  ************  */


  public function count(){
    return count($this->iterable);
  }


  /*  ************  Implementation of IteratorAggregate  ************  */

  
  public function getIterator(){
    return new ArrayIterator($this->iterable);
  }

}