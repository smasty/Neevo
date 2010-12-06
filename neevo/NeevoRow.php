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
 * Class representing a row in result.
 * @package Neevo
 */
class NeevoRow implements ArrayAccess, Countable, IteratorAggregate, Serializable {

  /** @var array */
  private $data = array();
  
  /** @var array */
  private $modified = array();
  
  /** @var NeevoResult */
  private $result;

  /** @var bool */
  private $single = false;

  /** @var mixed */
  private $singleValue;


  public function __construct($data, NeevoResult $result){
    $this->data = $data;
    if(count($data) === 1){
      $this->single = true;
      $keys = array_keys($this->data);
      $this->singleValue = $this->data[$keys[0]];
    }
    $this->result = $result;
  }


  /** @internal */
  public function __get($name){
    if($this->isSingle())
      return $this->singleValue;
    return $this->data[$name];
  }


  /** @internal */
  public function __set($name, $value){
    $this->modified[$name] = $value;
  }


  /** @internal */
  public function __isset($name){
    return isset($this->data[$name]);
  }


  /** @internal */
  public function __unset($name){
    unset($this->data[$name]);
  }


  /** @internal */
  public function __toString(){
    if($this->single === true)
      return (string) $this->singleValue;
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
    if($this->isSingle())
      return $this->singleValue;
  }


  /**
   * Object as an array
   * @return array
   */
  public function toArray(){
    return $this->data;
  }


  /**
   * **Experimental** Update row data
   *
   * After changing values in the NeevoRow instance, sends update query to server.
   * @return int Number of affected rows
   */
  public function update(){
    if(!empty($this->modified) && $this->modified !== $this->data){
      $q = $this->result;
      try{
        $primary = $q->getPrimary();
      } catch(NotImplementedException $e){
        return $this->result->neevo()->error('Functionality not implemented in this driver.');
      }
      if(!$this->data[$primary])
        return $this->result->neevo()->error('Cannot get primary_key value');

      return $q->neevo()->update($q->getTable(), $this->modified)->where($primary, $this->data[$primary])->limit(1)->affectedRows();
    }
  }


  /**
   * **Experimental** Deletes row
   * @return int Number of affected rows
   */
  public function delete(){
    $q = $this->result;
    try{
      $primary = $q->getPrimary();
    } catch(NotImplementedException $e){
      return $this->result->neevo()->error('Functionality not implemented in this driver.');
    }

    if($primary === null)
      return $this->result->neevo()->error('Cannot get primary_key value');

    return $q->neevo()->delete($q->getTable())->where($primary, $this->data[$primary])->limit(1)->affectedRows();
  }


  /* Implementation of Array Access */

  /** @internal */
  public function offsetSet($offset, $value){
    if(isset($this->data[$offset]))
      $this->modified[$offset] = $value;
  }


  /** @internal */
  public function offsetExists($offset){
    return isset($this->data[$offset]);
  }


  /** @internal */
  public function offsetUnset($offset){
    unset($this->modified[$offset]);
  }


  /** @internal */
  public function offsetGet($offset){
    return isset($this->modified[$offset]) ? $this->modified[$offset] :
      isset($this->data[$offset]) ? $this->data[$offset] : null;
  }


  /* Implementation of Countable */

  public function count(){
    return count($this->data);
  }


  /* Implementation of IteratorAggregate */

  /** @internal */
  public function getIterator(){
    return new ArrayIterator($this->data);
  }


  /* Implementation of Serializable */

  /** @internal */
  public function serialize(){
    return serialize($this->data);
  }

  
  /** @internal */
  public function unserialize($serialized){
    $this->data = unserialize($serialized);
  }

}