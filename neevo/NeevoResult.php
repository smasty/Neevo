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
 */
class NeevoResult implements ArrayAccess, Countable, Iterator {

  /** @var string */
  private $tableName;

  /** @var string */
  private $type;

  /** @var int */
  private $limit;
  
  /** @var int */
  private $offset;
  
  /** @var Neevo */
  private $neevo;

  /** @var mixed */
  private $resultSet;
  
  /** @var int */
  private $time;

  /** @var string */
  private $sql;
  
  /** @var bool */
  private $performed;

  /** @var int */
  private $numRows;
  
  /** @var int */
  private $affectedRows;

  /** @var array */
  private  $conditions = array();
  
  /** @var array */
  private $ordering = array();
  
  /** @var array */
  private $columns = array();

  /** @var array */
  private $values = array();

  /** @var array */
  private $data;

  /** @var array */
  private $keys;


  /**
   * Query base constructor
   * @param array $object Reference to instance of Neevo class which initialized Query
   * @param string $type Query type. Possible values: select, insert, update, delete
   * @param string $table Table to interact with
   * @return void
   */
  public function  __construct(Neevo $object){
    $this->neevo = $object;
  }


  public function  __destruct(){
    $this->free();
  }


  /**
   * Creates SELECT query
   * @param string|array $cols Columns to select (array or comma-separated list)
   * @param string $table Table name
   * @return NeevoResult fluent interface
   */
  public function select($cols = '*', $table){
    $this->type = 'select';
    $this->columns = is_string($cols) ? explode(',', $cols) : $cols;
    $this->tableName = $table;
    return $this;
  }


  /**
   * Creates UPDATE query
   * @param string $table Table name
   * @param array $data Data to update
   * @return NeevoResult fluent interface
   */
  public function update($table, array $data){
    $this->type = 'update';
    $this->tableName = $table;
    $this->values = $data;
    return $this;
  }


  /**
   * Creates INSERT query
   * @param string $table Table name
   * @param array $values Values to insert
   * @return NeevoResult fluent interface
   */
  public function insert($table, array $values){
    $this->type = 'insert';
    $this->tableName = $table;
    $this->values = $values;
    return $this;
  }


  /**
   * Alias for NeevoResult::insert()
   * @return NeevoResult fluent interface
   */
  public function insertInto($table, array $values){
    return $this->insert($table, $values);
  }


  /**
   * Creates DELETE query
   * @param string $table Table name
   * @return NeevoResult fluent interface
   */
  public function delete($table){
    $this->type = 'delete';
    $this->tableName = $table;
    return $this;
  }


  /**
   * Creates query with direct SQL
   * @param string $sql SQL code
   * @return NeevoResult fluent interface
   */
  public function sql($sql){
    $this->sql = $sql;
    $this->type = 'sql';
    return $this;
  }


  /**
   * Sets WHERE condition. More calls appends conditions.
   * @param string $where Column to use and optionaly operator: "email", "email LIKE", "email !=", etc.
   * @param string|array $value Value to search for: "string", "%patten%", array, boolean or NULL.
   * @return NeevoResult fluent interface
   */
  public function where($condition, $value = true){
    $condition = trim($condition);
    $column = strstr($condition, ' ') ? substr($condition, 0, strpos($condition, ' ')) : $condition;
    $operator = strstr($condition, ' ') ? substr($condition, strpos($condition, ' ')+1) : null;

    if(is_null($value)){
      $operator = 'IS';
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
    elseif(is_array($value))
      $operator = (strtoupper($operator) == 'NOT') ? 'NOT IN' : 'IN';

    if(!isset($operator)) $operator = '=';

    $this->conditions[] = array($column, $operator, $value, 'AND');
    return $this;
  }


  /**
   * Sets AND/OR glue for WHERE conditions
   *
   * Use as regular method: $query->where('id', 5)->or()->where()...
   * @return NeevoResult
   */
  public function  __call($name, $arguments){
    if(in_array(strtolower($name), array('and', 'or'))){
      $this->conditions[max(array_keys($this->conditions))][3] = strtoupper($name);
      return $this;
    }
    return $this;
  }


  /**
   * Sets ORDER clauses. Accepts infinite arguments (rules).
   * @param string $rules Order rules: "column", "col1, col2 DESC", etc.
   * @return NeevoResult fluent interface
   */
  public function order($rules){
    $this->ordering = func_get_args();
    return $this;
  }


  /**
   * Alias for NeevoResult::order().
   * @return NeevoResult fluent interface
   */
  public function orderBy($rules){
    $this->ordering = func_get_args();
    return $this;
  }


  /**
   * Sets LIMIT and OFFSET clause.
   * @param int $limit
   * @param int $offset
   * @return NeevoResult fluent interface
   */
  public function limit($limit, $offset = null){
    $this->limit = $limit;
    if(isset($offset) && $this->type() == 'select')
      $this->offset = $offset;
    return $this;
  }


  /**
   * Randomize result order. Removes any other order clause.
   * @return NeevoResult fluent interface
   */
  public function rand(){
    try{
      $this->neevo->driver()->rand($this);
    }
    catch(NotImplementedException $e){
      return $this;
    }
    return $this;
  }


  /**
   * Prints out syntax highlighted query.
   * @param bool $return Return output instead of printing it?
   * @return string|NeevoResult fluent interface
   */
  public function dump($return = false){
    $code = (PHP_SAPI === 'cli') ? $this->build() : self::_highlightSql($this->build());
    if(!$return) echo $code;
    return $return ? $code : $this;
  }


  /**
   * Performs Query
   * @return resource|bool
   */
  public function run(){
    $start = explode(' ', microtime());
    try{
      $query = $this->neevo->driver()->query($this->build());
    }
    catch(NotImplementedException $e){
      $query = null;
      return false;
    }

    if(!$query){
      $this->neevo->error('Query failed');
      return false;
    }

    $this->neevo->incrementQueries();
    $this->neevo->setLast($this);

    $end = explode(" ", microtime());
    $time = round(max(0, $end[0] - $start[0] + $end[1] - $start[1]), 4);
    $this->time = $time;

    $this->performed = true;
    $this->resultSet = $query;

    try{
      $this->numRows = $this->neevo->driver()->rows($query);
    }
    catch(NotImplementedException $e){
      $this->numRows = 0;
    }
    try{
      $this->affectedRows = $this->neevo->driver()->affectedRows();
    }
    catch(NotImplementedException $e){
      $this->affectedRows = 0;
    }

    return $query;
  }


  /**
   * Base fetcher - fetches data as array.
   * @return array|FALSE
   * @internal
   */
  private function fetchPlain(){
    $rows = array();

    $resultSet = $this->isPerformed() ? $this->resultSet() : $this->run();

    if(!$resultSet) // Error
      return $this->neevo->error('Fetching result data failed');

    try{
      $rows = $this->neevo->driver()->fetchAll($resultSet);
    }
    catch(NotImplementedException $e){
      try{
        while($row = $this->neevo->driver()->fetch($resultSet))
          $rows[] = $row;
      }
      catch(NotImplementedException $e){
        return false;
      }
    }

    $this->free();

    if(empty($rows)) // Empty
      return false;

    return $rows;
  }


  /**
   * Fetches all data from given result set.
   *
   * Returns array with rows as **NeevoRow** instances or FALSE.
   * @return array|FALSE
   */
  public function fetch(){
    $result = $this->fetchPlain();
    if($result === false)
      return false;
    $rows = array();
    foreach($result as $row)
      $rows[] = new NeevoRow($row, $this);
    unset($result);
    $this->data = $rows;
    return $rows;
  }


  /**
   * Fetches the first row in result set.
   * @param bool $array Return as array instead of NeevoRow instance.
   * @return NeevoRow|array|false
   */
  public function fetchRow($array = false){
    $resultSet = $this->isPerformed() ? $this->resultSet() : $this->run();
    if(!$resultSet) // Error
      return $this->neevo->error('Fetching result data failed');

    try{
      $result = $this->neevo->driver()->fetch($resultSet);
    }
    catch(NotImplementedException $e){
      return false;
    }
    
    $this->free();
    if($result === false)
      return false;
    return $array ? $result : new NeevoRow($result, $this);
  }


  /**
   * Fetches the only value in result set.
   * @return mixed|FALSE
   */
  public function fetchSingle(){
    $result = $this->fetchRow();
    if($result === false)
      return false;
    if($result->isSingle())
      return $result->getSingle();
    else $this->neevo->error('More than one columns in the row, cannot fetch single');
  }


  /**
   * Fetches data as $key=>$value pairs.
   *
   * If $key and $value columns are not defined in SELECT statement, they will
   * be automatically added to statement and others will be removed.
   * @param string $key Column to use as an array key.
   * @param string $value Column to use as an array value.
   * @return array|FALSE
   */
  public function fetchPairs($key, $value){
    if(!in_array($key, $this->columns) || !in_array($value, $this->columns) || !in_array('*', $this->columns)){
      $this->columns = array($key, $value);
      $this->performed = false; // If query was executed without needed columns, force execution.
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
   * Fetches all data as **indexed array** with rows represented as **associative arrays**.
   * @return array|FALSE
   */
  public function fetchArray(){
    return $this->fetchPlain();
  }


  /**
   * Fetches all data as associative arrays with $column as a 'key' to row.
   * @param string $column Column to use as key for row
   * @param bool $array Rows are returned as arrays instead of NeevoRow instances.
   * @return array|FALSE
   */
  public function fetchAssoc($column, $array = false){
    if(!in_array($column, $this->columns) || !in_array('*', $this->columns)){
      $this->columns[] = $column;
      $this->performed = false; // If query was executed without needed column, force execution.
    }
    $result = $this->fetchPlain();
    if($result === false)
      return false;

    $rows = array();
    foreach($result as $row){
      if(!$array)
        $row = new NeevoRow($row, $this); // Rows as NeevoRow.
      $rows[$row[$column]] = $row;
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
    }  catch(NotImplementedException $e){}
    $this->resultSet = null;
  }


  /**
   * Move internal result pointer
   * @param int $offset Row number of the new result pointer.
   * @return bool
   */
  public function seek($offset){
    if(!$this->isPerformed()) $this->run();

    try{
      $seek = $this->neevo->driver()->seek($this->resultSet(), $offset);
    }
    catch(NotImplementedException $e){
      $seek = false;
    }
    return $seek ? $seek : $this->neevo->error("Cannot seek to offset $offset");
  }


  /**
   * Get the ID generated in the INSERT query
   * @return int|FALSE
   */
  public function insertId(){
    if(!$this->isPerformed()) $this->run();

    try{
      return $this->neevo->driver()->insertId();
    }
    catch(NotImplementedException $e){
      return false;
    }
  }


  /**
   * Number of rows in result set
   * @return int
   */
  public function rows(){
    if(!$this->isPerformed()) $this->run();
    return $this->numRows;
  }


  /**
   * Number of rows affected by query
   * @return int
   */
  public function affectedRows(){
    if(!$this->isPerformed()) $this->run();
    return $this->affectedRows;
  }


  /**
   * Builds Query from NeevoResult instance
   * @return string The Query in SQL dialect
   * @internal
   */
  public function build(){

    try{
      return $this->neevo->driver()->build($this);
    }
    catch(NotImplementedException $e){
      return '';
    }

  }


  /**
   * Basic information about query
   * @param bool $hide_password Password will be replaced by '*****'.
   * @param bool $exclude_connection Connection info will be excluded.
   * @return array
   */
  public function info($hide_password = true, $exclude_connection = false){
    $info = array(
      'type' => $this->type,
      'table' => $this->getTable(),
      'executed' => (bool) $this->isPerformed(),
      'query_string' => $this->dump(true)
    );

    if($exclude_connection == true)
      $this->neevo->connection()->info($hide_password);

    if($this->isPerformed()){
      $info['time'] = $this->time;
      if(isset($this->numRows))
        $info['rows'] = $this->numRows;
      if(isset($this->affectedRows))
        $info['affected_rows'] = $this->affectedRows;
      if($this->type == 'insert')
        $info['last_insert_id'] = $this->insertId();
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
   * @return Neevo
   * @internal
   */
  public function neevo(){
    return $this->neevo;
  }


  /** @internal */
  public function resultSet(){
    return $this->resultSet;
  }

  /**
   * If query was performed, returns true.
   * @return bool
   */
  public function isPerformed(){
    return $this->performed;
  }

  /**
   * Full table name (with prefix)
   * @return string
   */
  public function getTable(){
    $table = $this->tableName;
    $prefix = $this->neevo->connection()->prefix();
    if(preg_match('#([^.]+)(\.)([^.]+)#', $table))
      return str_replace('.', ".$prefix", $table);
    return $prefix.$table;
  }

  /**
   * Query type
   * @return string
   */
  public function getType(){
    return $this->type;
  }

  /**
   * Query LIMIT fraction
   * @return int
   */
  public function getLimit(){
    return $this->limit;
  }

  /**
   * Query OFFSET fraction
   * @return int
   */
  public function getOffset(){
    return $this->offset;
  }

  /**
   * Query code for direct queries (type=sql)
   * @return string
   */
  public function getSql(){
    return $this->sql;
  }

  /**
   *Query WHERE conditions
   * @return array
   */
  public function getConditions(){
    return $this->conditions;
  }

  /**
   * Query ORDER BY fraction
   * @return array
   */
  public function getOrdering(){
    return $this->ordering;
  }

  /**
   * Query columns fraction for SELECT queries ([SELECT] col1, col2, ...)
   * @return array
   */
  public function getColumns(){
    return $this->columns;
  }

  /**
   * Query values fraction for INSERT/UPDATE queries
   *
   * [INSERT INTO tbl] (col1, col2, ...) VALUES (val1, val2, ...) or
   * [UPDATE tbl] SET col1 = val1,  col2 = val2, ...
   * @return array
   */
  public function getValues(){
    return $this->values;
  }

  /**
   * Name of PRIMARY KEY if defined, NULL otherwise.
   * @return string
   */
  public function getPrimary(){
    $return = null;
    $table = preg_replace('#[^0-9a-z_.]#i', '', $this->getTable());
    $cached_primary = $this->neevo->cacheLoad($table.'_primaryKey');

    if(is_null($cached_primary)){
      $return = $this->neevo->driver()->getPrimaryKey($table);
      $this->neevo->cacheSave($table.'_primaryKey', $return);
      return $return;
    }
    return $cached_primary;
  }


  /*  ******  Internal methods  ******  */


  /**
   * Highlights given SQL code
   * @param string $sql
   * @return string
   * @internal
   */
  private static function _highlightSql($sql){
    $keywords1 = 'SELECT|UPDATE|INSERT\s+INTO|DELETE|FROM|VALUES|SET|WHERE|HAVING|GROUP\s+BY|ORDER\s+BY|LIMIT|OFFSET|LEFT\s+JOIN|INNER\s+JOIN';
    $keywords2 = 'RANDOM|RAND|ASC|DESC|USING|AND|OR|ON|IN|IS|NOT|NULL|LIKE|TRUE|FALSE|AS';

    $sql = str_replace("\\'", '\\&#39;', $sql);
    $sql = preg_replace_callback("~($keywords1)|($keywords2)|('[^']+'|[0-9]+)|(/\*.*\*/)|(--\s?[^;]+)|(#[^;]+)~", array('NeevoResult', '_highlightCallback'), $sql);
    $sql = str_replace('\\&#39;', "\\'", $sql);
    return '<code style="color:#555" class="sql-dump">' . $sql . "</code>\n";
  }


  private static function _highlightCallback($match){
    if(!empty($match[1])) // Basic keywords
      return '<strong style="color:#e71818">'.$match[1].'</strong>';
    if(!empty($match[2])) // Other keywords
      return '<strong style="color:#d59401">'.$match[2].'</strong>';
    if(!empty($match[3])) // Values
      return '<em style="color:#008000">'.$match[3].'</em>';
    if(!empty($match[4])) // C-style comment
      return '<em style="color:#999">'.$match[4].'</em>';
    if(!empty($match[5])) // Dash-dash comment
      return '<em style="color:#999">'.$match[5].'</em>';
    if(!empty($match[6])) // hash comment
      return '<em style="color:#999">'.$match[6].'</em>';
  }


  /* Implementation of Array Access */

  /** @internal */
  public function offsetSet($offset, $value){
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


  /* Implementation of Countable */

  public function count(){
    return count($this->data);
  }


  /* Implementation of iterator */

  /** @internal */
  public function rewind() {
		$this->fetch();
		$this->keys = array_keys($this->data);
		reset($this->keys);
	}


  /** @internal */
  public function current() {
		return $this->data[current($this->keys)];
	}


  /** @internal */
	public function key() {
		return current($this->keys);
	}


  /** @internal */
	public function next() {
		next($this->keys);
	}


  /** @internal */
	public function valid() {
		return current($this->keys) !== false;
	}

}
