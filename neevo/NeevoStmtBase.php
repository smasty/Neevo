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
 * @method NeevoStmtBase end()
 *
 * @author Martin Srank
 * @package Neevo
 */
abstract class NeevoStmtBase {

  /** @var string */
  protected $tableName;

  /** @var string */
  protected $type;

  /** @var int */
  protected $limit;

  /** @var int */
  protected $offset;

  /** @var float */
  protected $time;

  /** @var bool */
  protected $performed;

  /** @var array */
  protected $whereFilters = array();

  /** @var array */
  protected $ordering = array();

  /** @var array */
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
   * | Condition with modifiers
   * |-------------------------------
   * | `where(':field != %s', 'x')` | `field != 'x'`
   * | `where(':field != %s OR :field < %i', 'x', 15)` | `field != 'x' OR field < 15`
   * | `where(':field LIKE %s', '%x%')` | `field LIKE '%x%'`
   * | `where(':field NOT IN %a', array(1, 2))` | `field NOT IN(1, 2)`
   * <br>
   * 
   * @param string $expr
   * @param mixed $value
   * @return NeevoStmtBase fluent interface
   */
  public function where($expr, $value = true){
    if(is_array($expr) && $value === true){
      return call_user_func_array(array($this, 'where'), $expr);
    }

    if($this->checkCond()){
      return $this;
    }
    $this->reinit();

    // Simple format
    if(strpos($expr, '%') === false){
      $field = trim($expr);
      $this->whereFilters[] = array(
        'simple' => true,
        'field' => $field,
        'value' => $value,
        'glue' => 'AND'
      );
      return $this;
    }

    // Format with modifiers
    $args = func_get_args();
    array_shift($args);
    preg_match_all('~%(b|i|f|s|bin|d|a|l)?~i', $expr, $matches);
    $this->whereFilters[] = array(
      'simple' => false,
      'expr' => $expr,
      'modifiers' => $matches[0],
      'types' => $matches[1],
      'values' => $args,
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
   * Define order. More calls append rules.
   * @param string|array $rule
   * @param string $order Use constants - Neevo::ASC, Neevo::DESC
   * @return NeevoStmtBase fluent interface
   */
  public function order($rule, $order = null){
    if($this->checkCond()){
      return $this;
    }
    $this->reinit();
    
    if(is_array($rule)){
      foreach($rule as $key => $val){
        $this->order($key, $val);
      }
      return $this;
    }
    $this->ordering[] = array($rule, $order);

    return $this;
  }

  /**
   * @deprecated
   * @internal
   */
  public function orderBy(){
    return call_user_func_array(array($this, 'order'), func_get_args());
  }

  /**
   * Set LIMIT and OFFSET clauses.
   * @param int $limit
   * @param int $offset
   * @return NeevoStmtBase fluent interface
   */
  public function limit($limit, $offset = null){
    if($this->checkCond()){
      return $this;
    }
    $this->reinit();
    $this->limit = array($limit, ($offset !== null && $this->type === Neevo::STMT_SELECT) ? $offset : null);
    return $this;
  }

  /**
   * Randomize order. Removes any other order clause.
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
   * @param bool $return
   * @return string|NeevoStmtBase fluent interface
   */
  public function dump($return = false){
    $sql = (PHP_SAPI === 'cli') ? $this->parse() : Neevo::highlightSql($this->parse());
    if(!$return){
      echo $sql;
    }
    return $return ? $sql : $this;
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
   * If query was performed.
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
    $table = str_replace(':', '', $table);
    $prefix = $this->connection->prefix();
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
   * Get LIMIT and OFFSET clauses.
   * @return int
   */
  public function getLimit(){
    return $this->limit;
  }

  /**
   * Statement WHERE clause.
   * @return array
   */
  public function getConditions(){
    return $this->whereFilters;
  }

  /**
   * Statement ORDER BY clause.
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

  public function getForeignKey($table){
    $primary = $this->getPrimaryKey();
    return $table . '_' . ($primary !== null ? $primary : 'id' );
  }


  /*  ************  Internal methods  ************  */

  /**
   * Create clone of object.
   * @return void
   */
  public function __clone(){
    $this->reinit();
  }

  /** @internal */
  protected function realConnect(){
    return $this->connection->realConnect();
  }

  /**
   * @return NeevoConnection
   * @internal
   */
  public function connection(){
    return $this->connection;
  }

  /**
   * @return INeevoDriver
   * @internal
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
