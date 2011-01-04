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
 * Neevo statement abstract base ancestor
 * @package Neevo
 * @method NeevoStmtBase and() and( ) Sets AND glue for WHERE conditions, provides fluent interface
 * @method NeevoStmtBase or() or( ) Sets OR glue for WHERE conditions, provides fluent interface
 */
abstract class NeevoStmtBase extends NeevoAbstract {

  /** @var string */
  protected $tableName;

  /** @var string */
  protected $type;

  /** @var int */
  protected $limit;

  /** @var int */
  protected $offset;

  /** @var int */
  protected $time;

  /** @var bool */
  protected $performed;

  /** @var array */
  protected  $conditions = array();

  /** @var array */
  protected $ordering = array();


  /**
   * Sets WHERE condition. More calls appends conditions.
   *
   * Possible combinations for where conditions:
   * | Condition  | SQL code
   * |-----------------------
   * | `where('field', 'x')`             | `field = 'x'`
   * | `where('field !=', 'x')`          | `filed != 'x'`
   * | `where('field LIKE', '%x%')`      | `field LIKE '%x%'`
   * | `where('field', true)`            | `field`
   * | `where('field', false)`           | `NOT field`
   * | `where('field', null)`            | `field IS NULL`
   * | `where('field', array(1, 2))`     | `field IN(1, 2)`
   * | `where('field NOT', array(1, 2))` | `field NOT IN(1,2)`
   * | `where('field', new NeevoLiteral('NOW()'))`  | `field = NOW()`
   * @param string $condition
   * @param string|array|bool|null $value
   * @return NeevoStmtBase fluent interface
   */
  public function where($condition, $value = true){
    $this->reinit();
    $condition = trim($condition);
    $column = strstr($condition, ' ') ? substr($condition, 0, strpos($condition, ' ')) : $condition;
    $operator = strstr($condition, ' ') ? substr($condition, strpos($condition, ' ')+1) : null;

    if(is_null($value)){
      if(strtoupper($operator) === 'NOT'){
        $operator = ' NOT';
      }
      $operator = 'IS' . (string) $operator;
      $value = 'NULL';
    }
    elseif($value === true){
      $operator = '';
      $value = true;
    }
    elseif($value === false){
      $operator = '';
      $value = false;
    }
    elseif(is_array($value)){
      $operator = (strtoupper($operator) == 'NOT') ? 'NOT IN' : 'IN';
    }

    if(!isset($operator)){
      $operator = '=';
    }

    $this->conditions[] = array($column, $operator, $value, 'AND');
    return $this;
  }


  /**
   * Sets AND/OR glue for WHERE conditions
   * @return NeevoStmtBase fluent interface
   * @internal
   */
  public function  __call($name, $args){
    if(in_array(strtolower($name), array('and', 'or'))){
      $this->reinit();
      $this->conditions[max(array_keys($this->conditions))][3] = strtoupper($name);
      if(count($args) >= 1){
        $this->where($args[0], isset($args[1]) ? $args[1] : true);
      }
      return $this;
    }
    return $this;
  }


  /**
   * Sets ORDER clauses. Accepts infinite arguments (rules) or array.
   * @param string|array $rules Order rules: "column", "col1, col2 DESC", etc.
   * @return NeevoStmtBase fluent interface
   */
  public function order($rules){
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
    if(is_array($rules)){
      return $this->order($rules);
    }
    else return $this->order(func_get_args());
  }


  /**
   * Sets LIMIT and OFFSET clause.
   * @param int $limit
   * @param int $offset
   * @return NeevoStmtBase fluent interface
   */
  public function limit($limit, $offset = null){
    $this->reinit();
    $this->limit = $limit;
    if(isset($offset) && $this->type == Neevo::STMT_SELECT){
      $this->offset = $offset;
    }
    return $this;
  }


  /**
   * Randomize order. Removes any other order clause.
   * @return NeevoStmtBase fluent interface
   */
  public function rand(){
    $this->reinit();
    $this->neevo->driver()->rand($this);
    return $this;
  }


  /**
   * Prints out syntax highlighted statement.
   * @param bool $return Return output instead of printing it?
   * @return string|NeevoStmtBase fluent interface
   */
  public function dump($return = false){
    $code = (PHP_SAPI === 'cli') ? $this->build() : self::_highlightSql($this->build());
    if(!$return){
      echo $code;
    }
    return $return ? $code : $this;
  }


  /**
   * Performs statement
   * @return resource|bool
   */
  public function run(){
    $this->realConnect();

    $start = explode(' ', microtime());
    $query = $this->performed ?
      $this->resultSet : $this->neevo->driver()->query($this->build());

    $end = explode(" ", microtime());
    $time = round(max(0, $end[0] - $start[0] + $end[1] - $start[1]), 4);
    $this->time = $time;

    $this->performed = true;
    $this->resultSet = $query;

    $this->neevo->setLast($this->info());

    return $query;
  }


  /**
   * Builds statement from NeevoStmtBase instance
   * @return string The statement in SQL dialect
   * @internal
   */
  public function build(){
    return $this->neevo->stmtBuilder()->build($this);
  }


  /**
   * Basic information about statement
   * @param bool $hide_password Password will be replaced by '*****'.
   * @param bool $exclude_connection Connection info will be excluded.
   * @return array
   */
  public function info($hide_password = true, $exclude_connection = false){
    $info = array(
      'type' => substr($this->type, 5),
      'table' => $this->getTable(),
      'executed' => (bool) $this->isPerformed(),
      'query_string' => strip_tags($this->dump(true))
    );

    if($exclude_connection){
      $this->neevo->connection()->info($hide_password);
    }

    if($this->isPerformed()){
      $info['time'] = $this->time;
      if(isset($this->numRows)){
        $info['rows'] = $this->numRows;
      }
      if(isset($this->affectedRows)){
        $info['affected_rows'] = $this->affectedRows;
      }
      if($this->type == Neevo::STMT_INSERT){
        $info['last_insert_id'] = $this->insertId();
      }
    }

    return $info;
  }


  /*  ******  Setters & Getters  ******  */


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


  /** @internal */
  private function reinit(){
    $this->performed = false;
  }

  /**
   * Full table name (with prefix)
   * @return string
   */
  public function getTable($table = null){
    if($table === null){
      $table = $this->tableName;
    }
    $prefix = $this->neevo->connection()->prefix();
    if(preg_match('~([^.]+)(\.)([^.]+)~', $table)){
      return str_replace('.', ".$prefix", $table);
    }
    return $prefix.$table;
  }

  /**
   * Statement type
   * @return string
   */
  public function getType(){
    return $this->type;
  }

  /**
   * Statement LIMIT fraction
   * @return int
   */
  public function getLimit(){
    return $this->limit;
  }

  /**
   * Statement OFFSET fraction
   * @return int
   */
  public function getOffset(){
    return $this->offset;
  }

  /**
   * Statement WHERE conditions
   * @return array
   */
  public function getConditions(){
    return $this->conditions;
  }

  /**
   * Statement ORDER BY fraction
   * @return array
   */
  public function getOrdering(){
    return $this->ordering;
  }


  /**
   * Name of PRIMARY KEY column
   * @return string|null
   */
  public function getPrimaryKey(){
    $table = $this->getTable();
    $key = null;
    $cached = $this->neevo->cacheLoad($table.'_primaryKey');

    if($cached === null){
      try{
        $key = $this->neevo->driver()->getPrimaryKey($table);
      } catch(Exception $e){
        return null;
      }
      $this->neevo->cacheSave($table.'_primaryKey', $key);
      return $key === '' ? null : $key;
    }
    return $cached === '' ? null : $cached;
  }


  /*  ******  Internal methods  ******  */
  

  /** @internal */
  protected function realConnect(){
    return $this->neevo->connection->realConnect();
  }

  /**
   * Highlights given SQL code
   * @param string $sql
   * @return string
   * @internal
   */
  protected static function _highlightSql($sql){
    $keywords1 = 'SELECT|UPDATE|INSERT\s+INTO|DELETE|FROM|VALUES|SET|WHERE|HAVING|GROUP\s+BY|ORDER\s+BY|LIMIT|OFFSET|(?:LEFT |RIGHT |INNER )?JOIN';
    $keywords2 = 'RANDOM|RAND|ASC|DESC|USING|AND|OR|ON|IN|IS|NOT|NULL|LIKE|TRUE|FALSE|AS';

    $sql = str_replace("\\'", '\\&#39;', $sql);
    $sql = preg_replace_callback("~($keywords1)|($keywords2)|('[^']+'|[0-9]+)|(/\*.*\*/)|(--\s?[^;]+)|(#[^;]+)~", array('NeevoStmtBase', '_highlightCallback'), $sql);
    $sql = str_replace('\\&#39;', "\\'", $sql);
    return '<code style="color:#555" class="sql-dump">' . $sql . "</code>\n";
  }

  /** @internal */
  protected static function _highlightCallback($match){
    if(!empty($match[1])){ // Basic keywords
      return '<strong style="color:#e71818">'.$match[1].'</strong>';
    }
    if(!empty($match[2])){ // Other keywords
      return '<strong style="color:#d59401">'.$match[2].'</strong>';
    }
    if(!empty($match[3])){ // Values
      return '<em style="color:#008000">'.$match[3].'</em>';
    }
    if(!empty($match[4])){ // /* comment */
      return '<em style="color:#999">'.$match[4].'</em>';
    }
    if(!empty($match[5])){ // -- comment
      return '<em style="color:#999">'.$match[5].'</em>';
    }
    if(!empty($match[6])){ // # comment
      return '<em style="color:#999">'.$match[6].'</em>';
    }
  }


}
