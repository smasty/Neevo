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
 * @license  http://www.opensource.org/licenses/mit-license.php  MIT license
 * @link     http://neevo.smasty.net/
 * @package  Neevo
 *
 */

/**
 * Neevo result class
 * @package Neevo
 */
class NeevoResult implements ArrayAccess, Countable, IteratorAggregate {

  private $data = array();
  private $query;


  public function __construct(array $data, NeevoQuery $query){
    $this->query = $query;
    foreach($data as $key => $value)
      is_array($value) ? $this->data[$key] = new NeevoRow($value, $this->query()) : $this->data[$key] = $value;
  }
  
  
  /**
   * NeevoQuery instance which created this result
   * @return NeevoQuery
   */
  public function query(){
    return $this->query;
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
   * Returns object as an array
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


  /**
   * Dumps result
   * @param bool $return_string Return or output?
   * @return string
   */
  public function dump($return_dump = false){
    $return = '';
    if(!empty($this->data)){
      $count = count($this->data);
      foreach($this->data as $key => $value){
        $return .= '  '.$key.' => ';
        $len = strlen($value);

        if(is_bool($value))
          $return .= $value ? "(bool) <strong>TRUE</strong>" : "(bool) <strong>FALSE</strong>";

        elseif(is_int($value))
          $return .= "(int:$len) <strong>$value</strong>";

        elseif(is_float($value))
          $return .= "(float:$len) <strong>$value</strong>";

        elseif(is_numeric($value))
          $return .= "(int:$len) <strong>$value</strong>";

        elseif(is_string($value))
          $return .= "(string:$len) \"<strong>$value</strong>\"";

        elseif(is_object($value))
          $return .= str_replace(array("\n", '<pre class="dump">', '</pre>'), "\n    ", $value->dump(true));

        else $return .= "(unknown type) \"<strong>".(string) $value."</strong>\"";

        $return .= "\n";
      }
      $return = "<pre class=\"dump\">\n<strong>NeevoResult</strong> ($count) {\n$return}</pre>";
    }
    else $return = '<pre class="dump">\n<strong>NeevoResult</strong> (empty)</pre>';

    if($return_dump) return $return;
    echo $return;
  }
}

/**
 * Neevo row class
 * @package Neevo
 */
class NeevoRow implements ArrayAccess, Countable, IteratorAggregate, Serializable {

  private $data = array(), $modified = array(), $query, $single = false;


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
   * Returns object as an array
   * @return array
   */
  public function toArray(){
    return $this->data;
  }


  /**
   * NeevoQuery instance which created this result
   * @return NeevoQuery
   */
  public function query(){
    return $this->query;
  }


  public function update(){
    if(!empty($this->modified) && $this->modified !== $this->data){
      $q = $this->query();
      $primary = $q->getPrimary();
      if(!$this->data[$primary])
        return $this->query()->neevo()->error('Cannot get primary_key value');

      return $q->neevo()->update($q->getTable())->set($this->modified)->where($primary, $this->data[$primary])->limit(1)->run();
    }
  }


  public function delete(){
    $q = $this->query();
    $primary = $q->getPrimary();
    if($primary === null)
      return $this->query()->neevo()->error('Cannot get primary_key value');

    return $q->neevo()->delete($q->getTable())->where($primary, $this->data[$primary])->limit(1)->run();
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


  /**
   * Dumps row
   * @param bool $return_dump Return or output?
   * @return string
   */
  public function dump($return_dump = false){
    $return = '';
    if($this->single)
      $return = "(string:".strlen($this->data).") \"<strong>$this->data</strong>\"";
    
    else{
      if(!empty($this->data)){
        $count = count($this->data);
        foreach($this->data as $key => $value){
          $return .= "  [$key] => ";
          $len = strlen($value);
          $value = htmlspecialchars($value);

          if(is_bool($value))
            $return .= $value ? "(bool) <strong>TRUE</strong>" : "(bool) <strong>FALSE</strong>";

          elseif(is_int($value))
            $return .= "(int:$len) <strong>$value</strong>";

          elseif(is_float($value))
            $return .= "(float:$len) <strong>$value</strong>";

          elseif(is_numeric($value))
            $return .= "(num:$len) <strong>$value</strong>";

          elseif(is_string($value))
            $return .= "(string:$len) \"<strong>$value</strong>\"";

          else $return .= "(unknown type) \"<strong>".(string) $value."</strong>\"";

          $return .= "\n";
        }
        $return = "<pre class=\"dump\">\n<strong>NeevoRow</strong> ($count) {\n$return}</pre>";
      }
      else $return = '<pre class="dump">\n<strong>NeevoRow</strong> (empty)</pre>';
    }

    if($return_dump) return $return;
    echo $return;
  }

}