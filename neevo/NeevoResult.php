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
class NeevoResult extends NeevoStmtBase implements ArrayAccess, IteratorAggregate, Countable {


  /** @var mixed */
  protected $resultSet;

  /** @var int */
  protected $numRows;

  /** @var string */
  private $grouping;

  /** @var string */
  private $having = null;
  
  /** @var array */
  protected $columns = array();

  /** @var array */
  private $join;

  /** @var array */
  private $data;

  /** @var */
  private $rowClass = 'NeevoRow';


  /**
   * Creates SELECT statement
   * @param array $object Reference to instance of Neevo class which initialized statement
   * @param string|array $cols Columns to select (array or comma-separated list)
   * @param string $table Table name
   * @throws InvalidArgumentException
   * @return void
   */
  public function  __construct(Neevo $object, $cols = null, $table = null){
    $this->neevo = $object;

    if($cols == null && $table == null)
      throw new InvalidArgumentException('Missing argument 1 for '.__METHOD__);
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
   * Sets GROUP BY clause with optional HAVING.
   * @param string $rule
   * @param string $having Optional HAVING
   * @return NeevoResult fluent interface
   */
  public function group($rule, $having = null){
    $this->reinit();
    $this->grouping = $rule;
    if(is_string($having))
      $this->having = $having;
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
   * Performs JOIN on tables
   * @param string $table Join table
   * @param string $expr Join expression
   * @return NeevoResult fluent interface
   */
  public function join($table, $expr, $type = null){
    $this->reinit();
    $prefix = $this->neevo->connection()->prefix();

    if(!in_array($type, array(null, Neevo::JOIN_LEFT, Neevo::JOIN_RIGHT, Neevo::JOIN_INNER)))
      throw new InvalidArgumentException('Argument 3 passed to '.__METHOD__.' must be valid JOIN type or NULL.');
    
    $this->join = array(
      'type' => $type,
      'table' => $this->getTable($table),
      'expr' => preg_replace('~(\w+)\.(\w+)~i', "$1.$prefix$2", $expr)
    );
    return $this;
  }


  /**
   * Performs LEFT JOIN on tables
   * @param string $table Join table
   * @param string $expr Join expression
   * @return NeevoResult fluent interface
   */
  public function leftJoin($table, $expr){
    return $this->join($table, $expr, Neevo::JOIN_LEFT);
  }


  /**
   * Performs RIGHT JOIN on tables
   * @param string $table Join table
   * @param string $expr Join expression
   * @return NeevoResult fluent interface
   */
  public function rightJoin($table, $expr){
    return $this->join($table, $expr, Neevo::JOIN_RIGHT);
  }


  /**
   * Performs INNER JOIN on tables
   * @param string $table Join table
   * @param string $expr Join expression
   * @return NeevoResult fluent interface
   */
  public function innerJoin($table, $expr){
    return $this->join($table, $expr, Neevo::JOIN_INNER);
  }


  /**
   * Base fetcher - fetches data as array.
   * @return array|FALSE
   * @internal
   */
  private function fetchPlain(){
    $rows = array();

    $resultSet = $this->isPerformed() ? $this->resultSet : $this->run();

    if(!$resultSet) // Error
      return $this->neevo->error('Fetching data failed');

    try{
      $rows = $this->neevo->driver()->fetchAll($resultSet);
    } catch(NotImplementedException $e){
      while($row = $this->neevo->driver()->fetch($resultSet))
        $rows[] = $row;
      }

    $this->free();

    if(empty($rows)) // Empty
      return false;

    return $rows;
  }


  /**
   * Fetches data from given result set.
   * @param int $format Return format - Neevo::OBJECT (default) or Neevo::ASSOC.
   * @return array|FALSE
   */
  public function fetch($format = Neevo::OBJECT){
    $result = $this->fetchPlain();
    if($result === false)
      return false;
    if($format === Neevo::ASSOC){
      $this->data = $result;
      unset($result);
      return $this->data;
    }
    $rows = array();
    foreach($result as $row)
      $rows[] = new $this->rowClass($row);
    unset($result);
    $this->data = $rows;
    unset($rows);
    return $this->data;
  }


  /**
   * Fetches the first row in result set.
   * @param int $format Return format - Neevo::OBJECT (default) or Neevo::ASSOC.
   * @return NeevoRow|array|FALSE
   */
  public function fetchRow($format = Neevo::OBJECT){
    $resultSet = $this->isPerformed() ? $this->resultSet() : $this->run();
    if(!$resultSet) // Error
      return $this->neevo->error('Fetching data failed');

    $result = $this->neevo->driver()->fetch($resultSet);
    
    $this->free();
    if($result === false)
      return false;
    if($format == Neevo::OBJECT)
      $result = new $this->rowClass($result);
    return $result;
  }


  /**
   * Fetches the only value in result set.
   * @return mixed|FALSE
   */
  public function fetchSingle(){
    $result = $this->fetchRow(Neevo::ASSOC);
    if($result === false || $result === null)
      return false;

    if(count($result) == 1)
      return reset($result);

    else $this->neevo->error('More than one columns in the row, cannot fetch single');
  }


  /**
   * Fetches data as $key=>$value pairs.
   *
   * If $key and $value columns are not defined in the statement, they will
   * be automatically added to statement and others will be removed.
   * @param string $key Column to use as an array key.
   * @param string $value Column to use as an array value.
   * @return array|FALSE
   */
  public function fetchPairs($key, $value){
    if(!in_array($key, $this->columns) || !in_array($value, $this->columns) || !in_array('*', $this->columns)){
      $this->columns = array($key, $value);
      $this->performed = false; // If statement was executed without needed columns, force execution (with them only).
    }
    $result = $this->fetchPlain();
    if($result === false)
      return false;

    $rows = array();
    foreach($result as $row)
      $rows[$row[$key]] = $row[$value];
    unset($result);
    return $rows;
  }


  /**
   * Fetches all data as associative arrays with $column as a 'key' to row.
   * @param string $column Column to use as key for row
   * @param int $format Return format - Neevo::OBJECT (default) or Neevo::ASSOC.
   * @return array|FALSE
   */
  public function fetchAssoc($column, $format = Neevo::OBJECT){
    if(!in_array($column, $this->columns) || !in_array('*', $this->columns)){
      $this->columns[] = $column;
      $this->performed = false; // If statement was executed without needed column, force execution.
    }
    $result = $this->fetchPlain();
    if($result === false)
      return false;

    $rows = array();
    foreach($result as $row){
      if($format == Neevo::OBJECT)
        $row = new $this->rowClass($row); // Rows as NeevoRow.
      $rows[$row[$column]] = $row;
    }
    unset($result);
    return $rows;
  }


  /**
   * Deprecated, use fetch(Neevo::ASSOC) instead.
   * @deprecated
   */
  public function fetchArray(){
    if(Neevo::$ignoreDeprecated) return $this->fetch(Neevo::ASSOC);
    trigger_error(__METHOD__.' is deprecated, use '.__CLASS__.'::fetch(Neevo::ASSOC) instead', E_USER_DEPRECATED);
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
   * Move internal result pointer
   * @param int $offset
   * @return bool
   */
  public function seek($offset){
    if(!$this->isPerformed()) $this->run();
    $seek = $this->neevo->driver()->seek($this->resultSet(), $offset);
    return $seek ? $seek : $this->neevo->error("Cannot seek to offset $offset");
  }


  /**
   * Number of rows in result set
   * @return int
   */
  public function rows(){
    if(!$this->isPerformed()) $this->run();
    $this->numRows = $this->neevo->driver()->rows($this->resultSet);
    return intval($this->numRows);
  }


  /**
   * Implementation of Countable
   * @return int
   */
  public function count(){
    if(!$this->isPerformed()) $this->run();
    return (int) $this->numRows;
  }


  /*  ******  Setters & Getters  ******  */


  /**
   * Class to use as a row class
   * @param string $className
   * @return NeevoResult fluent interface
   */
  public function setRowClass($className){
    if(!class_exists($className))
      return $this->neevo->error("Cannot set row class '$className' - class does not exist");
    $this->rowClass = $className;
    return $this;
  }

  /** @internal */
  public function resultSet(){
    return $this->resultSet;
  }

  /** @internal */
  public function reinit(){
    $this->performed = false;
    $this->data = null;
    $this->resultSet = null;
  }

  /** @internal */
  public function getData(){
    return $this->data;
  }

  /**
   * Statement GROUP BY fraction
   * @return string
   */
  public function getGrouping(){
    return $this->grouping;
  }

  /**
   * Statement HAVING fraction
   * @return string
   */
  public function getHaving(){
    return $this->having;
  }

  /**
   * Statement columns fraction for SELECT statements ([SELECT] col1, col2, ...)
   * @return array
   */
  public function getColumns(){
    return $this->columns;
  }

  /**
   * Statement JOIN fraction
   * @return array|false
   */
  public function getJoin(){
    if(!empty($this->join))
      return $this->join;
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
    if(!$this->isPerformed())
      $this->fetch();
    return isset($this->data[$offset]);
  }


  /** @internal */
  public function offsetUnset($offset){
    unset($this->data[$offset]);
  }


  /** @internal */
  public function offsetGet($offset){
    if(!$this->isPerformed())
      $this->fetch();
    return isset($this->data[$offset]) ? $this->data[$offset] : null;
  }


  /* Implementation of IteratorAggregate */

  /** @return NeevoResultIterator */
  public function getIterator(){
    return new NeevoResultIterator($this);
  }

}
