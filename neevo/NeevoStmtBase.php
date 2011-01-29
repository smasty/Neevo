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
 * Neevo statement abstract base ancestor.
 *
 * @method NeevoStmtBase and($expr, $value = true)
 * @method NeevoStmtBase or($expr, $value = true)
 * @method NeevoStmtBase if($condition)
 * @method NeevoStmtBase else()
 * @method NeevoStmtBase elseif($condition)
 * @method NeevoStmtBase end()
 *
 * @author Martin Srank
 * @package Neevo
 */
abstract class NeevoStmtBase {

  protected $tableName, $type, $limit, $offset, $time, $performed;
  protected $whereFilters = array(), $ordering = array();
  protected $conditions = array();

  /** @var NeevoConnection */
  protected $connection;

  /** @var array Event type conversion table */
  protected static $eventTable = array(
    Neevo::STMT_SELECT => INeevoObserver::SELECT,
    Neevo::STMT_INSERT => INeevoObserver::INSERT,
    Neevo::STMT_UPDATE => INeevoObserver::UPDATE,
    Neevo::STMT_DELETE => INeevoObserver::DELETE
  );

  /**
   * Create statement.
   * @param NeevoConnection $connection
   * @return void
   */
  public function  __construct(NeevoConnection $connection){
    $this->connection = $connection;
  }

  /**
   * String representation of object.
   * @return string
   */
  public function __toString(){
    return (string) $this->parse();
  }
  
  /**
   * Set WHERE condition. Accepts infinite arguments.
   *
   * More calls append conditions with 'AND' operator. Conditions can also be specified
   * by calling and() / or() methods the same way as where().
   * Corresponding operator will be used.
   *
   * **Warning! When using placeholders, field names have to start
   * with '::' (double colon) in order to respect defined table prefix!**
   *
   * Possible combinations for where conditions:
   * | Condition  | SQL code
   * |-----------------------
   * | `where('field', 'x')` | `field = 'x'`
   * | `where('field', true)` | `field`
   * | `where('field', false)` | `NOT field`
   * | `where('field', null)` | `field IS NULL`
   * | `where('field', array(1, 2))` | `field IN(1, 2)`
   * | `where('field', new NeevoLiteral('NOW()'))` | `field = NOW()`
   * |-------------------------------
   * | Condition (with placeholders)
   * |-------------------------------
   * | `where('::field != %1', 'x')` | `filed != 'x'`
   * | `where('::field != %1 OR ::field < %2', 'x', 15)` | `filed != 'x' OR field < 15`
   * | `where('::field LIKE %1', '%x%')` | `field LIKE '%x%'`
   * | `where('::field NOT %1', array(1, 2))` | `field NOT IN(1, 2)`
   * <br>
   * 
   * @param string $expr
   * @param mixed $value
   * @return NeevoStmtBase fluent interface
   */
  public function where($expr, $value = true){
    if($this->checkCond()){
      return $this;
    }
    $this->reinit();

    // Simple format
    if(!preg_match('~%\d+~', $expr)){
      $field = trim($expr);
      $this->whereFilters[] = array(
        'simple' => true,
        'field' => $field,
        'value' => $value,
        'glue' => 'AND'
      );
      return $this;
    }

    // Format with placeholders
    $values = func_get_args();
    unset($values[0]);
    preg_match_all("~%\d+~", $expr, $match);
    $keys = array_flip($match[0]);
    $placeholders = array();
    foreach($values as $k => $v){
      if(isset($keys["%$k"])){
        $placeholders[] = $match[0][$keys["%$k"]];
      }
    }
    $this->whereFilters[] = array(
      'simple' => false,
      'expr' => $expr,
      'placeholders' => $placeholders,
      'values' => $values,
      'glue' => 'AND'
    );
    return $this;
  }

  /**
   * @return NeevoStmtBase fluent interface
   * @internal
   * @throws BadMethodCallException
   * @throws InvalidArgumentException
   */
  public function  __call($name, $args){
    $name = strtolower($name);

    // AND/OR where() glues
    if(in_array($name, array('and', 'or'))){
      if($this->checkCond()){
        return $this;
      }
      $this->reinit();
      $this->whereFilters[count($this->whereFilters)-1]['glue'] = strtoupper($name);
      if(count($args) >= 1){
        call_user_func_array(array($this, 'where'), $args);
      }
      return $this;
    }

    // Conditional statements
    elseif(in_array($name, array('if', 'else', 'end'))){

      // Parameter counts
      if(count($args) < 1 && $name == 'if'){
        throw new InvalidArgumentException('Missing argument 1 for '.__CLASS__."::$name().");
      }

      $conds = & $this->conditions;
      if($name == 'if'){
        $conds[] = (bool) $args[0];
      }
      elseif($name == 'else'){
        $conds[ count($conds)-1 ] = !end($conds);
      }
      elseif($name == 'end'){
        array_pop($conds);
      }

      return $this;

    }
    throw new BadMethodCallException('Call to undefined method '.__CLASS__."::$name()");
  }

  /** @internal */
  protected function checkCond(){
    if(empty($this->conditions)){
      return false;
    }
    foreach($this->conditions as $cond){
      if($cond) continue;
      else return true;
    }
  }

  /**
   * Set ORDER clauses. Accepts infinite arguments (rules) or an array.
   * @param string|array $rules Order rules: "column", "col1, col2 DESC", etc.
   * @return NeevoStmtBase fluent interface
   */
  public function order($rules){
    if($this->checkCond()){
      return $this;
    }
    $this->reinit();
    if(is_array($rules)){
      $this->ordering = $rules;
    }
    else{
      $this->ordering = func_get_args();
    }
    return $this;
  }

  /**
   * Alias for NeevoStmtBase::order().
   * @return NeevoStmtBase fluent interface
   */
  public function orderBy($rules){
    return $this->order(is_array($rules) ? $rules : func_get_args());
  }

  /**
   * Set LIMIT and OFFSET clause.
   * @param int $limit
   * @param int $offset
   * @return NeevoStmtBase fluent interface
   */
  public function limit($limit, $offset = null){
    if($this->checkCond()){
      return $this;
    }
    $this->reinit();
    $this->limit = $limit;
    if(isset($offset) && $this->type == Neevo::STMT_SELECT){
      $this->offset = $offset;
    }
    return $this;
  }

  /**
   * Randomize order. Removes any other order clauses.
   * @return NeevoStmtBase fluent interface
   */
  public function rand(){
    if($this->checkCond()){
      return $this;
    }
    $this->reinit();
    $this->driver()->rand($this);
    return $this;
  }

  /**
   * Print out syntax highlighted statement.
   * @param bool $return Return the output instead of printing it
   * @return string|NeevoStmtBase fluent interface
   */
  public function dump($return = false){
    $code = (PHP_SAPI === 'cli') ? $this->parse() : Neevo::highlightSql($this->parse());
    if(!$return){
      echo $code;
    }
    return $return ? $code : $this;
  }

  /**
   * Perform the statement.
   * @return resource|bool
   */
  public function run(){
    $this->realConnect();

    $start = -microtime(true);
    
    $query = $this->performed ?
      $this->resultSet : $this->driver()->query($this->parse());

    $this->time = $start + microtime(true);

    $this->performed = true;
    $this->resultSet = $query;

    $this->connection->notifyObservers(self::$eventTable[$this->type], $this);

    return $query;
  }

  /**
   * Perform the statement. Alias for run().
   * @return resource|bool
   */
  public function exec(){
    return $this->run();
  }

  /**
   * Build the SQL statement from the instance.
   * @return string The SQL statement
   * @internal
   */
  public function parse(){
    return $this->connection->stmtParser()->parse($this);
  }


  /*  ************  Getters  ************  */


  /**
   * Query execution time.
   * @return int
   */
  public function time(){
    return $this->time;
  }

  /**
   * If query was performed, returns true.
   * @return bool
   */
  public function isPerformed(){
    return $this->performed;
  }

  /**
   * Full table name (with prefix).
   * @return string
   */
  public function getTable($table = null){
    if($table === null){
      $table = $this->tableName;
    }
    $table = str_replace('::', '', $table);
    $prefix = $this->connection->prefix();
    if(preg_match('~([^.]+)(\.)([^.]+)~', $table)){
      return str_replace('.', ".$prefix", $table);
    }
    return $prefix.$table;
  }

  /**
   * Statement type.
   * @return string
   */
  public function getType(){
    return $this->type;
  }

  /**
   * Statement LIMIT fraction.
   * @return int
   */
  public function getLimit(){
    return $this->limit;
  }

  /**
   * Statement OFFSET fraction.
   * @return int
   */
  public function getOffset(){
    return $this->offset;
  }

  /**
   * Statement WHERE conditions.
   * @return array
   */
  public function getConditions(){
    return $this->whereFilters;
  }

  /**
   * Statement ORDER BY fraction.
   * @return array
   */
  public function getOrdering(){
    return $this->ordering;
  }

  /**
   * Name of the PRIMARY KEY column.
   * @return string|null
   */
  public function getPrimaryKey(){
    $table = $this->getTable();
    $key = null;
    $cached = $this->connection->cache()->fetch($table.'_primaryKey');

    if($cached === null){
      try{
        $key = $this->driver()->getPrimaryKey($table);
      } catch(Exception $e){
        return null;
      }
      $this->connection->cache()->store($table.'_primaryKey', $key);
      return $key === '' ? null : $key;
    }
    return $cached === '' ? null : $cached;
  }


  /*  ************  Internal methods  ************  */

  /**
   * Create clone of object.
   */
  public function __clone(){
    $this->reinit();
  }

  /** @internal */
  protected function realConnect(){
    return $this->connection->realConnect();
  }

  /**
   * @internal
   * @return NeevoConnection
   */
  public function connection(){
    return $this->connection;
  }

  /**
   * @internal
   * @return INeevoDriver
   */
  public function driver(){
    return $this->connection->driver();
  }

  /** @internal */
  public function reinit(){
    $this->performed = false;
    $this->time = null;
  }

  /** @internal */
  protected function getConfig($key = null){
    return $this->connection->getConfig($key);
  }

}
