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
 * Represents result. Can be iterated and provides fluent interface.
 * @package Neevo
 * @method NeevoResult and() and( ) Sets AND glue for WHERE conditions, provides fluent interface
 * @method NeevoResult or() or( ) Sets OR glue for WHERE conditions, provides fluent interface
 */
class NeevoResult extends NeevoStmtBase implements ArrayAccess, Iterator, Countable {

  protected $resultSet, $numRows, $grouping, $having = null, $columns = array();
  private $join, $iteratorPointer, $data, $dataFormat, $rowClass = 'NeevoRow';

  /**
   * Create SELECT statement.
   * @param array $neevo Neevo instance
   * @param string|array $cols Columns to select (array or comma-separated list)
   * @param string $table Table name
   * @throws InvalidArgumentException
   * @return void
   */
  public function  __construct(Neevo $neevo, $cols = null, $table = null){
    $this->neevo = $neevo;

    if($cols == null && $table == null){
      throw new InvalidArgumentException('Missing argument 2 for '.__METHOD__.'.');
    }
    if(func_get_arg(2) == null){
      $cols = '*';
      $table = func_get_arg(1);
    }
    $this->reinit();
    $this->type = Neevo::STMT_SELECT;
    $this->columns = is_string($cols) ? explode(',', $cols) : $cols;
    $this->tableName = $table;
  }


  public function  __destruct(){
    $this->free();
  }


  /**
   * Set GROUP BY clause with optional HAVING.
   * @param string $rule
   * @param string $having Optional HAVING
   * @return NeevoResult fluent interface
   */
  public function group($rule, $having = null){
    $this->reinit();
    $this->grouping = $rule;
    if(is_string($having)){
      $this->having = $having;
    }
    return $this;
  }


  /**
   * Alias for NeevoResult::group().
   * @return NeevoResult fluent interface
   */
  public function groupBy($rule, $having = null){
    return $this->group($rule, $having);
  }


  /**
   * Perform JOIN on tables.
   * @param string $table Join table
   * @throws InvalidArgumentException
   * @return NeevoResult fluent interface
   */
  public function join($table, $type = null){
    $this->reinit();

    if(!in_array($type, array(null, Neevo::JOIN_LEFT, Neevo::JOIN_RIGHT, Neevo::JOIN_INNER))){
      throw new InvalidArgumentException('Argument 2 passed to '.__METHOD__.' must be valid JOIN type or NULL.');
    }
    
    $this->join = array(
      'type' => $type,
      'table' => $this->getTable($table)
    );
    return $this;
  }


  /**
   * Set JOIN operator 'ON'.
   * @param string $expr
   * @return NeevoResult fluent interface
   */
  public function on($expr){
    $this->join['operator'] = 'ON';
    $this->join['expr'] = $expr;

    return $this;
  }


  /**
   * Set JOIN operator 'USING'
   * @param string $expr
   * @return NeevoResult fluent interface
   */
  public function using($expr){
    $this->join['operator'] = 'USING';
    $this->join['expr'] = $expr;

    return $this;
  }


  /**
   * Perform LEFT JOIN on tables.
   * @param string $table Join table
   * @param string $expr Join expression
   * @return NeevoResult fluent interface
   */
  public function leftJoin($table){
    return $this->join($table, Neevo::JOIN_LEFT);
  }


  /**
   * Perform RIGHT JOIN on tables.
   * @param string $table Join table
   * @return NeevoResult fluent interface
   */
  public function rightJoin($table){
    return $this->join($table, Neevo::JOIN_RIGHT);
  }


  /**
   * Perform INNER JOIN on tables.
   * @param string $table Join table
   * @return NeevoResult fluent interface
   */
  public function innerJoin($table){
    return $this->join($table, Neevo::JOIN_INNER);
  }


  /**
   * Base fetcher - fetches data as array.
   * @throws NeevoException
   * @return array|FALSE
   * @internal
   */
  private function fetchPlain(){
    $rows = array();

    $resultSet = $this->isPerformed() ? $this->resultSet : $this->run();

    if(!$resultSet){ // Error
      throw new NeevoException('Fetching result failed.');
    }

    while($row = $this->neevo->driver()->fetch($resultSet)){
      $rows[] = $row;
    }

    $this->free();

    if(empty($rows)){ // Empty
      return false;
    }

    return $rows;
  }


  /**
   * Fetch data from the result set.
   * @param int $format Return format - Neevo::OBJECT (default) or Neevo::ASSOC.
   * @return NeevoResult|array|FALSE Iterable result, array or FALSE.
   */
  public function fetch($format = Neevo::OBJECT){
    $result = $this->fetchPlain();
    if($result === false){
      return false;
    }

    $this->data = $result;
    $this->dataFormat = $format;
    unset($result);

    return $format === Neevo::OBJECT ? $this : $this->data;
  }


  /**
   * Fetch the first row in the result set.
   * @param int $format Return format - Neevo::OBJECT (default) or Neevo::ASSOC
   * @throws NeevoException
   * @return NeevoRow|array|FALSE
   */
  public function fetchRow($format = Neevo::OBJECT){
    $resultSet = $this->isPerformed() ? $this->resultSet() : $this->run();
    if(!$resultSet){ // Error
      throw new NeevoException('Fetching result failed.');
    }

    $result = $this->neevo->driver()->fetch($resultSet);
    
    $this->free();
    if($result === false){
      return false;
    }
    return $format == Neevo::OBJECT ? new $this->rowClass($result, $this) : $result;
  }


  /**
   * Fetch single value in the result set.
   * @return mixed|FALSE
   */
  public function fetchSingle(){
    $result = $this->fetchRow(Neevo::ASSOC);
    if($result === false || $result === null){
      return false;
    }

    return reset($result);
  }


  /**
   * Fetch rows as $key=>$value pairs.
   * @param string $key Key column
   * @param string|NULL $value Value column. NULL for all specified columns.
   * @return array|FALSE
   */
  public function fetchPairs($key, $value = null){
    // If executed w/o needed cols, force exec w/ them.
    if(!in_array('*', $this->columns)){
      if(!in_array($key, $this->columns)){
        $this->reinit();
        $this->columns[] = $key;
      }
      if($value !== null && !in_array($value, $this->columns)){
        $this->reinit();
        $this->columns[] = $value;
      }
    }

    $result = $this->fetchPlain();
    if($result === false){
      return false;
    }

    $rows = array();
    foreach($result as $row){
      $rows[$row[$key]] = $value === null ? $row : $row[$value];
    }
    unset($result);
    return $rows;
  }


  /**
   * Free result set resource.
   */
  private function free(){
    try{
      $this->neevo->driver()->free($this->resultSet);
    } catch(NotImplementedException $e){}
    $this->resultSet = null;
  }


  /**
   * Move internal result pointer.
   * @param int $offset
   * @throws NeevoException
   * @return bool
   */
  public function seek($offset){
    $this->isPerformed() || $this->run();
    $seek = $this->neevo->driver()->seek($this->resultSet(), $offset);
    if($seek){
      return $seek;
    }
    throw new NeevoException("Cannot seek to offset $offset.");
  }


  /**
   * Get the number of rows in the result set.
   * @return int
   */
  public function rows(){
    $this->isPerformed() || $this->run();
    $this->numRows = $this->neevo->driver()->rows($this->resultSet);
    return intval($this->numRows);
  }


  /**
   * Implementation of Countable.
   * @return int
   */
  public function count(){
    return (int) $this->rows();
  }


  /*  ******  Setters & Getters  ******  */


  /**
   * Set class to use as a row class.
   * @param string $className
   * @throws NeevoException
   * @return NeevoResult fluent interface
   */
  public function setRowClass($className){
    if(!class_exists($className)){
      throw new NeevoException("Cannot set row class '$className'.");
    }
    $this->rowClass = $className;
    return $this;
  }

  /** @internal */
  public function resultSet(){
    return $this->resultSet;
  }

  /** @internal */
  private function reinit(){
    $this->performed = false;
    $this->data = null;
    $this->resultSet = null;
  }

  /**
   * Statement GROUP BY fraction.
   * @return string
   */
  public function getGrouping(){
    return $this->grouping;
  }

  /**
   * Statement HAVING fraction.
   * @return string
   */
  public function getHaving(){
    return $this->having;
  }

  /**
   * Statement columns fraction ([SELECT] col1, col2, ...).
   * @return array
   */
  public function getColumns(){
    return $this->columns;
  }

  /**
   * Statement JOIN fraction.
   * @return array|false
   */
  public function getJoin(){
    if(!empty($this->join)){
      return $this->join;
    }
    return false;
  }



  /*  ******  Internal methods  ******  */


  /* Implementation of Array Access */

  /** @internal */
  public function offsetSet($offset, $value){
    $this->data[$offset] = $value;
  }


  /** @internal */
  public function offsetExists($offset){
    $this->isPerformed() || $this->fetch();
    return isset($this->data[$offset]);
  }


  /** @internal */
  public function offsetUnset($offset){
    unset($this->data[$offset]);
  }


  /** @internal */
  public function offsetGet($offset){
    $this->isPerformed() || $this->fetch();
    if(!isset($this->data[$offset])){
      return null;
    }

    $current = $this->data[$offset];

    if($this->dataFormat == Neevo::OBJECT && !($current instanceof $this->rowClass)){
      $current = $this->data[$offset] = new $this->rowClass($current, $this);
    }

    return $current;
  }


  /* Implementation of Iterator */

  /** @internal */
  public function rewind(){
    if(!empty($this->data) || $this->data === null){ // Force execution for future loops
      $this->reinit();
      $this->fetch();
    }
    $this->iteratorPointer = 0;
  }

  /** @internal */
  public function key(){
    return $this->iteratorPointer;
  }

  /** @internal */
  public function next(){
    $this->iteratorPointer++;
  }

  /** @internal */
  public function current(){
    $current = $this->data[$this->iteratorPointer];

    if($this->dataFormat == Neevo::OBJECT && !($current instanceof $this->rowClass)){
      $current = $this->data[$this->iteratorPointer] = new $this->rowClass($current, $this);
    }

    return $current;
  }

  /** @internal */
  public function valid(){
    return !empty($this->data[$this->iteratorPointer]);
  }

}
