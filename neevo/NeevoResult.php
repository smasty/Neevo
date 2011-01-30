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
 * @author Martin Srank
 * @package Neevo
 */
class NeevoResult extends NeevoStmtBase implements IteratorAggregate, Countable {

  /** @var resource */
  protected $resultSet;
  
  /** @var int */
  protected $numRows;

  /** @var string */
  protected $grouping;
  
  /** @var string */
  protected $having = null;

  /** @var array */
  protected $columns = array();

  /** @var string */
  private $join;

  /** @var string */
  private $rowClass = 'NeevoRow';
  
  /** @var array */
  private $columnTypes = array();

  /** @var bool */
  private $detectTypes;

  /**
   * Create SELECT statement.
   * @param NeevoConnection $connection
   * @param string|array $columns
   * @param string $table
   * @return void
   * @throws InvalidArgumentException
   */
  public function  __construct(NeevoConnection $connection, $columns = null, $table = null){
    parent::__construct($connection);

    if($columns == null && $table == null){
      throw new InvalidArgumentException('Missing argument 2 for '.__METHOD__.'.');
    }
    if(func_get_arg(2) == null){
      $columns = '*';
      $table = func_get_arg(1);
    }
    $this->reinit();
    $this->type = Neevo::STMT_SELECT;
    $this->columns = is_string($columns) ? explode(',', $columns) : $columns;
    $this->tableName = $table;
    $this->detectTypes = (bool) $this->getConfig('detectTypes');

    $this->setRowClass($this->getConfig('rowClass'));
  }

  /**
   * Set GROUP BY clause with optional HAVING.
   * @param string $rule
   * @param string $having
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
   * @param string $table
   * @return NeevoResult fluent interface
   * @throws InvalidArgumentException
   */
  public function join($table, $type = null){
    if($this->checkCond()){
      return $this;
    }
    $this->reinit();

    if(!in_array($type, array(null, Neevo::JOIN_LEFT, Neevo::JOIN_INNER))){
      throw new InvalidArgumentException('Argument 2 passed to '.__METHOD__.' must be valid JOIN type or NULL.');
    }
    
    $this->join = array(
      'type' => $type,
      'table' => $this->getTable($table)
    );
    return $this;
  }

  /**
   * Perform LEFT JOIN on tables.
   * @param string $table
   * @return NeevoResult fluent interface
   */
  public function leftJoin($table){
    return $this->join($table, Neevo::JOIN_LEFT);
  }

  /**
   * Perform INNER JOIN on tables.
   * @param string $table
   * @return NeevoResult fluent interface
   */
  public function innerJoin($table){
    return $this->join($table, Neevo::JOIN_INNER);
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
   * Fetch the row on current position.
   * @return NeevoRow|FALSE
   */
  public function fetch(){
    $this->performed || $this->run();
    
    $row = $this->driver()->fetch($this->resultSet);
    if(!is_array($row)){
      return false;
    }

    // Type converting
    if($this->detectTypes){
      $this->detectTypes();
    }
    if(!empty($this->columnTypes)){
      foreach($this->columnTypes as $col => $type){
        if(isset($row[$col])){
          $row[$col] = $this->convertType($row[$col], $type);
        }
      }
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
   * @return array
   * @throws NeevoException
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
    $this->performed || $this->run();
    $row = $this->driver()->fetch($this->resultSet);
    
    if(!$row) return false;
    $value = reset($row);
    
    // Type converting
    if($this->detectTypes){
      $this->detectTypes();
    }
    if(!empty($this->columnTypes)){
      $key = key($row);
      if(isset($this->columnTypes[$key])){
        $value = $this->convertType($value, $this->columnTypes[$key]);
      }
    }

    return $value;
  }

  /**
   * Fetch rows as $key=>$value pairs.
   * @param string $key Key column
   * @param string $value Value column. NULL for all specified columns.
   * @return array
   */
  public function fetchPairs($key, $value = null){
    $clone = clone $this;

    // If executed w/o needed cols, force exec w/ them.
    if(!in_array('*', $clone->columns)){
      if(!in_array($key, $clone->columns)){
        $clone->columns[] = $key;
      }
      if($value !== null && !in_array($value, $clone->columns)){
        $clone->columns[] = $value;
      }
    }

    $rows = array();
    while($row = $clone->fetch()){
      if(!$row) return array();
      $rows[$row[$key]] = $value === null ? $row : $row->$value;
    }

    return $rows;
  }

  /**
   * Free result set resource.
   * @return void
   */
  private function free(){
    try{
      $this->driver()->free($this->resultSet);
    } catch(NeevoImplemenationException $e){}
    $this->resultSet = null;
  }

  /**
   * Move internal result pointer.
   * @param int $offset
   * @return bool
   * @throws NeevoException
   */
  public function seek($offset){
    $this->performed || $this->run();
    $seek = $this->driver()->seek($this->resultSet, $offset);
    if($seek){
      return $seek;
    }
    throw new NeevoException("Cannot seek to offset $offset.");
  }

  /**
   * Get the number of rows in the result set.
   * @return int
   * @throws NeevoDriverException
   */
  public function rows(){
    $this->performed || $this->run();

    $this->numRows = (int) $this->driver()->rows($this->resultSet);
    return $this->numRows;
  }

  /**
   * Implementation of Countable.
   * @return int
   * @throws NeevoDriverException
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

  /**
   * Set column type.
   * @param string $column
   * @param string $type
   * @return NeevoResult fluent interface
   */
  public function setType($column, $type){
    $this->columnTypes[$column] = $type;
    return $this;
  }

  /**
   * Set multiple column types at once.
   * @param array $types
   * @return NeevoResult fluent interface
   */
  public function setTypes(array $types){
    foreach($types as $column => $type){
      $this->setType($column, $type);
    }
    return $this;
  }

  /**
   * Detect column types.
   * @return NeevoResult fluent interface
   */
  public function detectTypes(){
    $table = $this->getTable();
    $this->performed || $this->run();

    // Try fetch from cache
    $cached = $this->connection->cache()->fetch($table.'_detectedTypes');
    $types = $cached !== null
      ? $cached : $this->driver()->getColumnTypes($this->resultSet, $table);

    foreach($types as $col => $type){
      $this->columnTypes[$col] = $cached !== null ? $type : $this->resolveType($type);
    }
    // Store in cache
    if($cached === null){
      $this->connection->cache()->store($table.'_detectedTypes', $this->columnTypes);
    }
    return $this;
  }

  /**
   * Resolve vendor column type.
   * @param string $type
   * @return string
   */
  private function resolveType($type){
    static $patterns = array(
      'bool|bit' => Neevo::BOOL,
      'bin|blob|bytea' => Neevo::BINARY,
      'string|char|text|bigint|longlong' => Neevo::TEXT,
      'int|long|byte|serial|counter' => Neevo::INT,
      'float|real|double|numeric|number|decimal|money|currency' => Neevo::FLOAT,
      'time|date|year' => Neevo::DATETIME
    );

    foreach($patterns as $vendor => $universal){
      if(preg_match("~$vendor~i", $type)){
        return $universal;
      }
    }
    return Neevo::TEXT;
  }

  private function convertType($value, $type){
    $dateFormat = $this->getConfig('formatDateTime');
    if($value === null || $value === false){
      return null;
    }
    switch($type){
      case Neevo::TEXT:
        return (string) $value;

      case Neevo::INT:
        return (int) $value;

      case Neevo::FLOAT:
        return (float) $value;

      case Neevo::BOOL:
        return ((bool) $value) && $value !== 'f' && $value !== 'F';

      case Neevo::BINARY:
        return $this->driver()->unescape($value, $type);

      case Neevo::DATETIME:
        if((int) $value === 0){
          return null;
        }
        elseif(!$dateFormat){
          return new DateTime(is_numeric($value) ? date('Y-m-d H:i:s', $value) : $value);
        }
        elseif($dateFormat == 'U'){
          return is_numeric($value) ? (int) $value : strtotime($value);
        }
        elseif(is_numeric($value)){
          return date($dateFormat, $value);
        }
        else{
          $d = new DateTime($value);
          return $d->format($value);
        }

      default:
        return $value;
    }
  }


  /*  ************  Getters  ************  */


  /**
   * Get the result iterator.
   * @return NeevoResultIterator
   */
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
    parent::reinit();
    $this->resultSet = null;
    $this->numRows = null;
  }

  public function  __destruct(){
    $this->free();
  }

}
