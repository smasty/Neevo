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
 * Represents a result. Can be iterated, counted and provides fluent interface.
 * @package Neevo
 */
class NeevoResult extends NeevoStmtBase implements IteratorAggregate, Countable {

  protected $resultSet, $numRows, $grouping, $having = null, $columns = array();
  private $join, $rowClass = 'NeevoRow';

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
   * Fetch the row on current position.
   * @return NeevoRow|FALSE
   */
  public function fetch(){
    $resultSet = $this->performed ? $this->resultSet : $this->run();
    
    $row = $this->neevo->driver()->fetch($resultSet);
    if(!is_array($row)){
      return false;
    }

    return new $this->rowClass($row, $this);
  }

  /**
   * @deprecated
   */
  public function fetchRow(){
    return $this->fetch();
  }

  /**
   * Fetch all rows in result set.
   * @param int $limit Limit number of returned rows
   * @param int $offset Seek to offset (fails on unbuffered results)
   * @throws NeevoException
   * @return array
   */
  public function fetchAll($limit = null, $offset = null){
    $limit = ($limit === null) ? -1 : (int) $limit;
    if($offset !== null){
      $this->seek((int) $offset);
    }

    $row = $this->fetch();
    if(!$row){
      return array();
    }

    $rows = array();
    do{
      if($limit === 0){
        break;
      }
      $rows[] = $row;
      $limit--;
    } while($row = $this->fetch());

    return $rows;
  }

  /**
   * Fetch the first value from current row.
   * @return mixed
   */
  public function fetchSingle(){
    $resultSet = $this->performed ? $this->resultSet : $this->run();
    $row = $this->neevo->driver()->fetch($resultSet);
    
    if(!$row) return false;
    return reset($row);
  }

  /**
   * Fetch rows as $key=>$value pairs.
   * @param string $key Key column
   * @param string|NULL $value Value column. NULL for all specified columns.
   * @return array
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

    $rows = array();
    while($row = $this->fetch()){
      if(!$row) return array();
      $rows[$row[$key]] = $value === null ? $row : $row->$value;
    }

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
    $this->performed || $this->run();
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
    $this->performed || $this->run();

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


  /*  ************  Getters  ************  */


  public function  getIterator(){
    return new NeevoResultIterator($this);
  }

  public function getGrouping(){
    return $this->grouping;
  }

  public function getHaving(){
    return $this->having;
  }

  public function getColumns(){
    return $this->columns;
  }

  public function getJoin(){
    if(!empty($this->join)){
      return $this->join;
    }
    return false;
  }


  /*  ************  Internal methods  ************  */


  /** @internal */
  public function reinit(){
    $this->performed = false;
    $this->resultSet = null;
  }

}
