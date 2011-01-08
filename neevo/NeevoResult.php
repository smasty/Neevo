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
 * Represents result. Can be iterated, accessed as array and provides fluent interface.
 * @package Neevo
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
    if($this->checkCond()){
      return $this;
    }
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
    if($this->checkCond()){
      return $this;
    }
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
    if($this->checkCond()){
      return $this;
    }
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
    if($this->checkCond()){
      return $this;
    }
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
   * Fetch all data from the result set at once.
   * @param int $format Return format - Neevo::OBJECT (default) or Neevo::ASSOC.
   * @return NeevoResult|array|FALSE Iterable result, array or FALSE.
   */
  public function fetchAll($format = Neevo::OBJECT){
    $result = $this->fetchPlain();
    if(!$result){
      return false;
    }

    $this->data = $result;
    $this->dataFormat = $format;
    unset($result);

    return $format === Neevo::OBJECT ? $this : $this->data;
  }

  /**
   * Fetch the data ftom  the result set
   * @param int $format Return format - Neevo::OBJECT (default) or Neevo::ASSOC.
   * @return NeevoResult|FALSE Iterable instance
   */
  public function fetch($format = Neevo::OBJECT){
    $row = $this->fetchRow();
    if(!$row){
      return false;
    }
    $this->data[] = $row;
    $this->dataFormat = $format;

    return $this;
  }

  /**
   * Fetch the current row from the result set.
   * @param int $format Return format - Neevo::OBJECT (default) or Neevo::ASSOC
   * @throws NeevoException
   * @return NeevoRow|array|FALSE
   */
  public function fetchRow($format = Neevo::OBJECT){
    $resultSet = $this->isPerformed() ? $this->resultSet : $this->run();
    if(!$resultSet){ // Error
      throw new NeevoException('Fetching result failed.');
    }

    $result = $this->neevo->driver()->fetch($resultSet);
    
    //$this->free();
    if(!$result){
      return false;
    }
    return $format == Neevo::OBJECT ? new $this->rowClass($result, $this) : $result;
  }

  /**
   * Fetch single value from the result set.
   * @return mixed|FALSE
   */
  public function fetchSingle(){
    $result = $this->fetchRow(Neevo::ASSOC);
    if(!$result){
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
    if(!$result){
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
    $seek = $this->neevo->driver()->seek($this->resultSet, $offset);
    if($seek){
      return $seek;
    }
    throw new NeevoException("Cannot seek to offset $offset.");
  }

  /**
   * Get the number of rows in the result set.
   * @return int
   * @throws NotSupportedException
   */
  public function rows(){
    $this->isPerformed() || $this->run();

    $this->numRows = (int) $this->neevo->driver()->rows($this->resultSet);
    return $this->numRows;
  }

  /**
   * Implementation of Countable.
   * @return int
   * @throws NotSupportedException
   */
  public function count(){
    return $this->rows();
  }

  /**
   * Set class to use as a row class.
   * @param string $className
   * @return NeevoResult fluent interface
   * @throws NeevoException
   */
  public function setRowClass($className){
    if(!class_exists($className)){
      throw new NeevoException("Cannot set row class '$className'.");
    }
    $this->rowClass = $className;
    return $this;
  }


  /*  ************  Implementation of Array Access  ************  */


  /**
   * Check if row of given offset exists in the resultSet.
   *
   * This is a bit tricky because of lazy fetching.
   * At first, method checks whether there is a result of given offset stored or not.
   * If seek() is not available (e.g. unbuffered result), first, all rows
   * are fetched and stored and the checking is done on given offset.
   * If seek() is available, Neevo seeks to given offset and tries to fetch the Row.
   * If it succeed, the row is stored and the check is done on it.
   * @param string|int $offset
   * @return bool
   */
  public function offsetExists($offset){
    // Offset already stored
    if(is_array($this->data) && array_key_exists($offset, $this->data)){
      return !empty($this->data[$offset]);
    }

    // Try seeking to the offset
    try{
      $this->seek($offset);
    } catch(NotSupportedException $ed){
      // Seek not supported -> get all rows & return current
        if(empty($this->data)){
          $this->fetchAll();
        }
        return !empty($this->data[$offset]);
    } catch(NeevoException $en){
      // Offset overflow (offset not in resultSet)
        return false;
    }

    // Fetch the row, store it & return
    $this->data[$offset] = $this->fetchRow();
    return !empty($this->data[$offset]);
  }

  /**
   * Get the row of given offset from the resultSet.
   *
   * This is a bit tricky because of lazy fetching. See offsetExists().
   * This method does the same as offsetExists(), because it can be called without calling
   * offsetExists().
   * @param scalar $offset
   * @return object|array|null
   */
  public function offsetGet($offset){
    // Offset already stored
    if(is_array($this->data) && array_key_exists($offset, $this->data)){
      if(empty($this->data[$offset])){
        return null;
      }
      return $this->instantiateRow($this->data[$offset]);
    }

    // Try seeking to the offset
    try{
      $this->seek($offset);
    } catch(NotSupportedException $ed){
      // Seek not supported -> get all rows & return current/null
        if(empty($this->data)){
          $this->fetchAll();
        }
        if(empty($this->data[$offset])){
          return null;
        }
        return $this->instantiateRow($this->data[$offset]);
    } catch(NeevoException $en){
      // Offset overflow (offset not in resultSet)
        return null;
    }

    // Fetch the row & store it
    $this->data[$offset] = $this->fetchRow();
    return $this->instantiateRow($this->data[$offset]);
  }

  /**
   * @throws NeevoException
   */
  public function offsetSet($offset, $value){
    throw new NeevoException('Cannot set offset value.');
  }

  /**
   * Unset the value on given offset
   * @param scalar $offset
   * @return void
   */
  public function offsetUnset($offset){
    unset($this->data[$offset]);
  }


  /*  ************  Implementation of Iterator  ************  */


  /**
   * Rewind internal iterator pointer. Force execution of statement for all future loops.
   * @return void
   */
  public function rewind(){
    // Force execution for future loops - data may changed.
    if(!empty($this->data) || $this->data === null){
      $this->reinit();
    }
    $this->iteratorPointer = 0;
  }

  /**
   * Get the current key in iteration.
   * @return int
   */
  public function key(){
    return $this->iteratorPointer;
  }

  /**
   * Get the key of the current element
   * @return void
   */
  public function next(){
    $this->iteratorPointer++;
  }

  /**
   * Get the current row in resultSet in iteration.
   * @return object|array
   */
  public function current(){
    return $this->instantiateRow($this->data[$this->iteratorPointer]);
  }

  /**
   * Check if current row in resultSet is valid in iteration.
   *
   * This is a bit tricky because of lazy fetching. See offsetExists().
   * @return bool
   */
  public function valid(){
    // Position already stored
    if(is_array($this->data) && array_key_exists($this->iteratorPointer, $this->data)){
      return !empty($this->data[$this->iteratorPointer]);
    }

    // Fetch the row, store it & return
    $this->data[$this->iteratorPointer] = $this->fetchRow();
    return !empty($this->data[$this->iteratorPointer]);
  }

  /*  ************  Getters  ************  */

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


  /*  ************  Internal methods  ************  */


  private function instantiateRow(& $row){
    if(!is_array($row) && !($row instanceof $this->rowClass)){
      throw new InvalidArgumentException('Argument 1 passed to '.__METHOD__.' must be an instance of NeevoResult::rowClass or array.');
    }
    if(is_array($row)){
      if( (isset($this->dataFormat) && $this->dataFormat === Neevo::OBJECT) || !isset($this->dataFormat) ){
        $row = new $this->rowClass($row, $this);
      }
    }

    return $row;
  }

  private function reinit(){
    $this->performed = false;
    $this->data = null;
    $this->resultSet = null;
  }

}
