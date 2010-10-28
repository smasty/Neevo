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
 * Class representing result with one or more rows.
 * @package Neevo
 */
class NeevoResult implements ArrayAccess, Countable, IteratorAggregate {

  /** @var array */
  private $data = array();

  /**  @var NeevoQuery */
  private $query;


  public function __construct(array $data, NeevoQuery $query){
    $this->query = $query;
    foreach($data as $key => $value)
      is_array($value) ? $this->data[$key] = new NeevoRow($value, $this->query()) : $this->data[$key] = $value;
  }


  /**
   * Data in result
   * @return array
   */
  public function data(){
    return $this->data;
  }


  /* Implementation of Array Access */

  /** @internal */
  public function offsetSet($offset, $value){
    if(is_null($offset))
      $this->data[] = $value;
    else
      $this->data[$offset] = $value;
  }


  /** @internal */
  public function offsetExists($offset){
    return isset($this->data[$offset]);
  }


  /** @internal */
  public function offsetUnset($offset){
    unset($this->data[$offset]);
  }


  /** @internal */
  public function offsetGet($offset){
    return isset($this->data[$offset]) ? $this->data[$offset] : null;
  }


  /**
   * Object as an array
   * @return array
   */
  public function toArray(){
    if(!$this->data[0] instanceof NeevoRow)
      return $this->data();
    
    $rows = array();
    foreach($this->data() as $row){
      $rows[] = $row->toArray();
    }
    return $rows;
  }


  /* Implementation of Countable */

  /**
   * Number of rows in result
   * @return int
   */
  public function count(){
    return count($this->data);
  }


  /* Implementation of IteratorAggregate */

  /** @internal */
  public function getIterator(){
    return new ArrayIterator($this->data);
  }

}



/**
 * Class representing a row in result.
 * @package Neevo
 */
class NeevoRow implements ArrayAccess, Countable, IteratorAggregate, Serializable {

  /** @var array */
  private $data = array();
  
  /** @var array */
  private $modified = array();
  
  /** @var NeevoQuery */
  private $query;

  /** @var bool */
  private $single = false;


  public function __construct($data, NeevoQuery $query){
    $this->data = $data;
    if(count($data) === 1){
      $this->single = true;
      $keys = array_keys($this->data);
      $this->data = $this->data[$keys[0]];
    }
    $this->query = $query;
  }


  /** @internal */
  public function __get($name){
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
    if($this->single === true) return (string) $this->data;
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
      return $this->data;
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
      $q = $this->query;
      try{
        $primary = $q->getPrimary();
      } catch(NotImplementedException $e){
        return $this->query->neevo()->error('Functionality not implemented in this driver.');
      }
      if(!$this->data[$primary])
        return $this->query->neevo()->error('Cannot get primary_key value');

      return $q->neevo()->update($q->getTable(), $this->modified)->where($primary, $this->data[$primary])->limit(1)->affectedRows();
    }
  }


  /**
   * **Experimental** Deletes row
   * @return int Number of affected rows
   */
  public function delete(){
    $q = $this->query;
    try{
      $primary = $q->getPrimary();
    } catch(NotImplementedException $e){
      return $this->query->neevo()->error('Functionality not implemented in this driver.');
    }

    if($primary === null)
      return $this->query->neevo()->error('Cannot get primary_key value');

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