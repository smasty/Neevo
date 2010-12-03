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
class NeevoResult implements ArrayAccess, IteratorAggregate, Countable {

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

  /** @var string */
  private $grouping;

  /** @var string */
  private $having = null;
  
  /** @var array */
  private $columns = array();

  /** @var array */
  private $values = array();

  /** @var array */
  private $data;

  // Query types
  const TYPE_SELECT = 'type_select';
  const TYPE_INSERT = 'type_insert';
  const TYPE_UPDATE = 'type_update';
  const TYPE_DELETE = 'type_delete';
  const TYPE_SQL = 'type_sql';


  /**
   * Query base constructor
   * @param array Reference to instance of Neevo class which initialized Query
   * @param string Query type. Possible values: select, insert, update, delete
   * @param string Table to interact with
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
   * @param string|array Columns to select (array or comma-separated list)
   * @param string Table name
   * @return NeevoResult fluent interface
   */
  public function select($cols = '*', $table){
    $this->reinit();
    $this->type = self::TYPE_SELECT;
    $this->columns = is_string($cols) ? explode(',', $cols) : $cols;
    $this->tableName = $table;
    return $this;
  }


  /**
   * Creates UPDATE query
   * @param string Table name
   * @param array Data to update
   * @return NeevoResult fluent interface
   */
  public function update($table, array $data){
    $this->reinit();
    $this->type = self::TYPE_UPDATE;
    $this->tableName = $table;
    $this->values = $data;
    return $this;
  }


  /**
   * Creates INSERT query
   * @param string Table name
   * @param array Values to insert
   * @return NeevoResult fluent interface
   */
  public function insert($table, array $values){
    $this->reinit();
    $this->type = self::TYPE_INSERT;
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
   * @param string Table name
   * @return NeevoResult fluent interface
   */
  public function delete($table){
    $this->reinit();
    $this->type = self::TYPE_DELETE;
    $this->tableName = $table;
    return $this;
  }


  /**
   * Creates query with direct SQL
   * @param string SQL code
   * @return NeevoResult fluent interface
   */
  public function sql($sql){
    $this->reinit();
    $this->type = self::TYPE_SQL;
    $this->sql = $sql;
    return $this;
  }


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
   * @param string
   * @param string|array|bool|null   * @return NeevoResult fluent interface
   */
  public function where($condition, $value = true){
    $this->reinit();
    $condition = trim($condition);
    $column = strstr($condition, ' ') ? substr($condition, 0, strpos($condition, ' ')) : $condition;
    $operator = strstr($condition, ' ') ? substr($condition, strpos($condition, ' ')+1) : null;

    if(is_null($value)){
      if(strtoupper($operator) === 'NOT')
        $operator = ' NOT';
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
    elseif(is_array($value))
      $operator = (strtoupper($operator) == 'NOT') ? 'NOT IN' : 'IN';

    if(!isset($operator)) $operator = '=';

    $this->conditions[] = array($column, $operator, $value, 'AND');
    return $this;
  }


  /**
   * Sets AND/OR glue for WHERE conditions
   *
   * Use as regular method: $query->where('id', 5)->**or()**->where()...
   * @return NeevoResult fluent interface
   */
  public function  __call($name, $arguments){
    if(in_array(strtolower($name), array('and', 'or'))){
      $this->reinit();
      $this->conditions[max(array_keys($this->conditions))][3] = strtoupper($name);
      return $this;
    }
    return $this;
  }


  /**
   * Sets ORDER clauses. Accepts infinite arguments (rules) or array.
   * @param string|array Order rules: "column", "col1, col2 DESC", etc.
   * @return NeevoResult fluent interface
   */
  public function order($rules){
    $this->reinit();
    if(is_array($rules))
      $this->ordering = $rules;
    else
      $this->ordering = func_get_args();
    return $this;
  }


  /**
   * Alias for NeevoResult::order().
   * @return NeevoResult fluent interface
   */
  public function orderBy($rules){
    if(is_array($rules))
      return $this->order($rules);
    else return $this->order(func_get_args());
  }


  /**
   * Sets GROUP BY clause with optional HAVING.
   * @param string
   * @param string Optional HAVING
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
   * Sets LIMIT and OFFSET clause.
   * @param int
   * @param int
   * @return NeevoResult fluent interface
   */
  public function limit($limit, $offset = null){
    $this->reinit();
    $this->limit = $limit;
    if(isset($offset) && $this->type == self::TYPE_SELECT)
      $this->offset = $offset;
    return $this;
  }


  /**
   * Randomize result order. Removes any other order clause.
   * @return NeevoResult fluent interface
   */
  public function rand(){
    $this->reinit();
    $this->neevo->driver()->rand($this);
    return $this;
  }


  /**
   * Prints out syntax highlighted query.
   * @param bool Return output instead of printing it?
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

    $query = $this->neevo->driver()->query($this->build());

    if(!$query){
      return $this->neevo->error('Query failed');
    }

    $end = explode(" ", microtime());
    $time = round(max(0, $end[0] - $start[0] + $end[1] - $start[1]), 4);
    $this->time = $time;

    $this->performed = true;
    $this->resultSet = $query;

    $this->neevo->setLast($this->info());

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
    $this->data  = $rows;
    return $this->data;
  }


  /**
   * Fetches the first row in result set.
   *
   * Format can be:
   * - Neevo::OBJECT - returned as NeevoRow instance (**default**)
   * - Neevo::ASSOC - returned as associative array
   * @param int Return format
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
      $result = new NeevoRow($result, $this);
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
   * @param string Column to use as an array key.
   * @param string Column to use as an array value.
   * @return array|FALSE
   */
  public function fetchPairs($key, $value){
    if(!in_array($key, $this->columns) || !in_array($value, $this->columns) || !in_array('*', $this->columns)){
      $this->columns = array($key, $value);
      $this->performed = false; // If query was executed without needed columns, force execution (with them only).
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
   * Fetches all data as array with rows as associative arrays.
   * @return array|FALSE
   */
  public function fetchArray(){
    return $this->fetchPlain();
  }


  /**
   * Fetches all data as associative arrays with $column as a 'key' to row.
   *
   * Format can be:
   * - Neevo::OBJECT - returned as NeevoRow instance (**default**)
   * - Neevo::ASSOC - returned as associative array
   * @param string Column to use as key for row
   * @param int Return format
   * @return array|FALSE
   */
  public function fetchAssoc($column, $format = Neevo::OBJECT){
    if(!in_array($column, $this->columns) || !in_array('*', $this->columns)){
      $this->columns[] = $column;
      $this->performed = false; // If query was executed without needed column, force execution.
    }
    $result = $this->fetchPlain();
    if($result === false)
      return false;

    $rows = array();
    foreach($result as $row){
      if($format == Neevo::OBJECT)
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
    } catch(NotImplementedException $e){}
    $this->resultSet = null;
  }


  /**
   * Move internal result pointer
   * @param int Row number of the new result pointer.
   * @return bool
   */
  public function seek($offset){
    if(!$this->isPerformed()) $this->run();
    $seek = $this->neevo->driver()->seek($this->resultSet(), $offset);
    return $seek ? $seek : $this->neevo->error("Cannot seek to offset $offset");
  }


  /**
   * Get the ID generated in the INSERT query
   * @return int|FALSE
   */
  public function insertId(){
    if(!$this->isPerformed()) $this->run();

    return $this->neevo->driver()->insertId();
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
   * Number of rows affected by query
   * @return int
   */
  public function affectedRows(){
    if(!$this->isPerformed()) $this->run();
    $this->affectedRows = $this->neevo->driver()->affectedRows();
    return $this->affectedRows;
  }


  /**
   * Implementation of Countable
   * @return int
   */
  public function count(){
    if(!$this->isPerformed()) $this->run();
    return (int) $this->numRows;
  }


  /**
   * Builds Query from NeevoResult instance
   * @return string The Query in SQL dialect
   * @internal
   */
  public function build(){

    try{
      return $this->neevo->queryBuilder()->build($this);
    } catch(NotImplementedException $e){
        return '';
      }

  }


  /**
   * Basic information about query
   * @param bool Password will be replaced by '*****'.
   * @param bool Connection info will be excluded.
   * @return array
   */
  public function info($hide_password = true, $exclude_connection = false){
    $info = array(
      'type' => substr($this->type, 5),
      'table' => $this->getTable(),
      'executed' => (bool) $this->isPerformed(),
      'query_string' => substr(strip_tags($this->dump(true)), 0, -1)
    );

    if($exclude_connection == true)
      $this->neevo->connection()->info($hide_password);

    if($this->isPerformed()){
      $info['time'] = $this->time;
      if(is_int($this->numRows))
        $info['rows'] = $this->numRows;
      if(is_int($this->affectedRows))
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
   * Query GROUP BY fraction
   * @return string
   */
  public function getGrouping(){
    return $this->grouping;
  }

  /**
   * Query HAVING fraction
   * @return string
   */
  public function getHaving(){
    return $this->having;
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
   * @param string
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
