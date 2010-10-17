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
 * @license  http://www.opensource.org/licenses/mit-license.php  MIT license
 * @link     http://neevo.smasty.net
 * @package  Neevo
 *
 */

/**
 * Neevo class for SQL query abstraction
 * @package Neevo
 */
class NeevoQuery {

  private  $table, $type, $limit, $offset, $neevo, $resultSet, $time, $sql, $performed, $numRows, $affectedRows;
  private  $where = array(), $order = array(), $columns = array(), $data = array();


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


  /**
   * Creates SELECT query.
   * @param string|array $cols Columns to select (array or comma-separated list)
   * @return NeevoQuery fluent interface
   */
  public function select($cols){
    $this->setType('select');
    $this->columns = is_string($cols) ? explode(',', $cols) : $cols;
    return $this;
  }


  /**
   * Sets table for SELECT and DELETE queries.
   * @param string $table
   * @return NeevoQuery fluent interface
   */
  public function from($table){
    $this->setTable($table);
    return $this;
  }


  /**
   * Creates UPDATE query.
   * @param string $table Table name
   * @return NeevoQuery fluent interface
   */
  public function update($table){
    $this->setType('update');
    $this->setTable($table);
    return $this;
  }


  /**
   * Sets data for UPDATE query.
   * @param array $data Data to update.
   * @return NeevoQuery
   */
  public function set(array $data){
    $this->data = $data;
    return $this;
  }


  /**
   * Creates INSERT query.
   * @param string $table Table name
   * @return NeevoQuery fluent interface
   */
  public function insert($table){
    $this->setType('insert');
    $this->setTable($table);
    return $this;
  }


  /**
   * Alias for NeevoQuery::insert().
   * @return NeevoQuery fluent interface
   */
  public function insertInto($table){
    return $this->insert($table);
  }


  /**
   * Sets values for INSERT query.
   * @param array $data Values.
   * @return NeevoQuery fluent interface
   */
  public function values(array $data){
    $this->data = $data;
    return $this;
  }


  /**
   * Creates DELETE query.
   * @param string $table Table name. Optional, can be set by from() method.
   * @return NeevoQuery fluent interface
   */
  public function delete($table = null){
    $this->setType('delete');
    if(isset($table))
      $this->setTable($table);
    return $this;
  }


  /**
   * Sets WHERE condition. More calls appends conditions.
   * @param string $where Column to use and optionaly operator: "email", "email LIKE", "email !=", etc.
   * @param string|array $value Value to search for: "string", "%patten%", array, boolean or NULL.
   * @param string $glue Glue (AND/OR) to use betweet this and next WHERE condition.
   * @return NeevoQuery fluent interface
   */
  public function where($condition, $value = true, $glue = 'AND'){
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

    $this->where[] = array($column, $operator, $value, strtoupper($glue));
    return $this;
  }


  /**
   * Sets ORDER clauses. Accepts infinite arguments (rules).
   * @param string $rules Order rules: "column", "col1, col2 DESC", etc.
   * @return NeevoQuery fluent interface
   */
  public function order($rules){
    $this->order = func_get_args();
    return $this;
  }


  /**
   * Alias for NeevoQuery::order().
   * @return NeevoQuery fluent interface
   */
  public function orderBy($rules){
    return $this->order($rule);
  }


  /**
   * Sets LIMIT and OFFSET clause.
   * @param int $limit
   * @param int $offset
   * @return NeevoQuery fluent interface
   */
  public function limit($limit, $offset = null){
    $this->limit = $limit;
    if(isset($offset) && $this->getType() == 'select')
      $this->offset = $offset;
    return $this;
  }


  /**
   * Randomize result order. Removes any other order clause.
   * @return NeevoQuery fluent interface
   */
  public function rand(){
    $this->neevo()->driver()->rand($this);
    return $this;
  }


  /**
   * Creates query with direct SQL.
   * @param string $sql SQL code
   * @return NeevoQuery fluent interface
   */
  public function sql($sql){
    $this->sql = $sql;
    $this->setType('sql');
    return $this;
  }


  /**
   * Prints consequential Query (highlighted by default)
   * @param bool $color Highlight query or not (default: yes)
   * @param bool $return_string Return the string or not (default: no)
   * @return NeevoQuery|void
   */
  public function dump($color = true, $return_string = false){
    $code = $color ? self::_highlightSql($this->build()) : $this->build();
    if(!$return_string) echo $code;
    return $return_string ? $code : $this;
  }


  /**
   * Performs Query
   * @return resource|bool
   */
  public function run(){
    $start = explode(' ', microtime());
    $query = $this->neevo()->driver()->query($this->build());

    if($query === false){
      $this->neevo()->error('Query failed');
      return false;
    }

    $this->neevo()->incrementQueries();
    $this->neevo()->setLast($this);

    $end = explode(" ", microtime());
    $time = round(max(0, $end[0] - $start[0] + $end[1] - $start[1]), 4);
    $this->setTime($time);

    $this->performed = true;
    if(is_resource($query)){
      $this->resultSet = $query;
      $this->numRows = $this->neevo()->driver()->rows($query);
    }
    else
      $this->affectedRows = $this->neevo()->driver()->affectedRows();

    return $query;
  }


  /**
   * Base fetcher - fetches data as array.
   * @return array|FALSE
   * @internal
   */
  private function fetchPlain(){
    $rows = array();
    if(!in_array($this->getType(), array('select', 'sql')))
      return $this->neevo()->error('Cannot fetch on this kind of query');

    $resultSet = $this->isPerformed() ? $this->resultSet() : $this->run();

    if(!is_resource($resultSet)) // Error
      return $this->neevo()->error('Fetching result data failed');

    while($row = $this->neevo()->driver()->fetch($resultSet))
      $rows[] = $row;

    $this->free();

    if(empty($rows)) // Empty
      return false;

    return $rows;
  }


  /**
   * Fetches all data from given Query resource.
   *
   * Returns **NeevoResult** instance with rows represented as **NeevoRow** instances or FALSE.
   * @return NeevoResult|FALSE
   */
  public function fetch(){
    $result = $this->fetchPlain();
    if($result === false)
      return false;
    $rows = array();
    foreach($result as $row)
      $rows[] = new NeevoRow($row, $this);
    unset($result);
    return new NeevoResult($rows, $this);
  }


  /**
   * Fetches 1st row in result as **NeevoRow** or the only value if only one row with one value was fetched.
   * @return NeevoRow|mixed|FALSE
   */
  public function fetchSingle(){
    $result = $this->fetchPlain();
    if($result === false)
      return false;

    if(count($result) > 1) // Return first row
      return new NeevoRow($result[0], $this);
    return $result[0][max(array_keys($result[0]))]; // Return the only value
  }


  /**
   * Fetches data as $key=>$value pairs.
   *
   * If $key and $value columns are not defined in SELECT statement, they will
   * be automatically added to statement and others will be removed.
   * @param string $key Column to use as array key.
   * @param string $value Column to use as array value.
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
   * @param bool $as_array Rows are arrays instead of NeevoRow instances.
   * @return array|FALSE
   */
  public function fetchAssoc($column, $as_array = false){
    if(!in_array($column, $this->columns) || !in_array('*', $this->columns)){
      $this->columns[] = $column;
      $this->performed = false; // If query was executed without needed column, force execution.
    }
    $result = $this->fetchPlain();
    if($result === false)
      return false;

    $rows = array();
    foreach($result as $row){
      if(!$as_array)
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
    $this->neevo()->driver()->free($this->resultSet);
    $this->resultSet = null;
  }


  /**
   * Move internal result pointer
   * @param int $row_number Row number of the new result pointer.
   * @return bool
   */
  public function seek($row_number){
    if(!$this->isPerformed()) $this->run();

    $seek = $this->neevo()->driver()->seek($this->resultSet(), $row_number);
    return $seek ? $seek : $this->neevo()->error("Cannot seek to row $row_number");
  }


  /**
   * Get the ID generated in the INSERT query
   * @return int
   */
  public function insertId(){
    if(!$this->isPerformed()) $this->run();

    return $this->neevo()->driver()->insertId();
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
   * Builds Query from NeevoQuery instance
   * @return string The Query in SQL dialect
   * @internal
   */
  public function build(){

    return $this->neevo()->driver()->build($this);

  }


  /**
   * Basic information about query
   * @param bool $hide_password Password will be replaced by '*****'.
   * @param bool $exclude_connection Connection info will be excluded.
   * @return array
   */
  public function info($hide_password = true, $exclude_connection = false){
    $info = array(
      'type' => $this->getType(),
      'table' => $this->getTable(),
      'executed' => (bool) $this->isPerformed(),
      'query_string' => $this->dump(false, true)
    );

    if($exclude_connection == true)
      $this->neevo()->connection()->info($hide_password);

    if($this->isPerformed()){
      $info['time'] = $this->time();
      if(isset($this->numRows))
        $info['rows'] = $this->numRows;
      if(isset($this->affectedRows))
        $info['affected_rows'] = $this->affectedRows;
      if($this->getType() == 'insert')
        $info['last_insert_id'] = $this->insertId();
    }

    return $info;
  }


  /*  ******  Setters & Getters  ******  */


  /** @internal */
  public function setTime($time){
    $this->time = $time;
  }

  /** @internal */
  public function setTable($table){
    $this->table = $table;
    return $this;
  }

  /** @internal */
  public function setType($type){
    $this->type = $type;
    return $this;
  }

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
    $table = $this->table;
    $prefix = $this->neevo()->connection()->prefix();
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
   *Query WHERE fraction
   * @return array
   */
  public function getWhere(){
    return $this->where;
  }

  /**
   * Query ORDER BY fraction
   * @return array
   */
  public function getOrder(){
    return $this->order;
  }

  /**
   * Query columns fraction for SELECT queries ([SELECT] col1, col2, ...)
   * @return array
   */
  public function getCols(){
    return $this->columns;
  }

  /**
   * Query values fraction for INSERT/UPDATE queries
   * ([INSERT INTO] (col1,, col2, ...) VALUES (val1, val2, ...) or
   *  [UPDATE tbl] SET col1 = val1,  col2 = val2, ...)
   * @return array
   */
  public function getData(){
    return $this->data;
  }

  /**
   * Name of PRIMARY KEY if defined, NULL otherwise.
   * @return string
   */
  public function getPrimary(){
    $return = null;
    $table = preg_replace('#[^0-9a-z_.]#i', '', $this->getTable());
    $cached_primary = $this->neevo()->cacheLoad('table_'.$table.'_primary');

    if(is_null($cached_primary)){
      $q = $this->neevo()->sql('SHOW FULL COLUMNS FROM '. $table);
      foreach($q->fetchArray() as $col){
        if($col['Key'] === 'PRI' && !isset($return))
          $return = $col['Field'];
      }
      $this->neevo()->cacheSave('table_'.$table.'_primary', $return);
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
    $color_codes = array('chars'=>'chars','keywords'=>'kwords','joins'=>'joins','functions'=>'funcs','constants'=>'consts');
    $colors = Neevo::$highlight_colors;
    unset($colors['columns']);

    $words = array(
      'keywords'  => array('SELECT', 'UPDATE', 'INSERT', 'DELETE', 'REPLACE', 'INTO', 'CREATE', 'ALTER', 'TABLE', 'DROP', 'TRUNCATE', 'FROM', 'ADD', 'CHANGE', 'COLUMN', 'KEY', 'WHERE', 'ON', 'CASE', 'WHEN', 'THEN', 'END', 'ELSE', 'AS', 'USING', 'USE', 'INDEX', 'CONSTRAINT', 'REFERENCES', 'DUPLICATE', 'LIMIT', 'OFFSET', 'SET', 'SHOW', 'STATUS', 'BETWEEN', 'AND', 'IS', 'NOT', 'OR', 'XOR', 'INTERVAL', 'TOP', 'GROUP BY', 'ORDER BY', 'DESC', 'ASC', 'COLLATE', 'NAMES', 'UTF8', 'DISTINCT', 'DATABASE', 'CALC_FOUND_ROWS', 'SQL_NO_CACHE', 'MATCH', 'AGAINST', 'LIKE', 'REGEXP', 'RLIKE', 'PRIMARY', 'AUTO_INCREMENT', 'DEFAULT', 'IDENTITY', 'VALUES', 'PROCEDURE', 'FUNCTION', 'TRAN', 'TRANSACTION', 'COMMIT', 'ROLLBACK', 'SAVEPOINT', 'TRIGGER', 'CASCADE', 'DECLARE', 'CURSOR', 'FOR', 'DEALLOCATE'),
      'joins'     => array('JOIN', 'INNER', 'OUTER', 'FULL', 'NATURAL', 'LEFT', 'RIGHT'),
      'functions' => array('MIN', 'MAX', 'SUM', 'COUNT', 'AVG', 'CAST', 'COALESCE', 'CHAR_LENGTH', 'LENGTH', 'SUBSTRING', 'DAY', 'MONTH', 'YEAR', 'DATE_FORMAT', 'CRC32', 'CURDATE', 'SYSDATE', 'NOW', 'GETDATE', 'FROM_UNIXTIME', 'FROM_DAYS', 'TO_DAYS', 'HOUR', 'IFNULL', 'ISNULL', 'NVL', 'NVL2', 'INET_ATON', 'INET_NTOA', 'INSTR', 'FOUND_ROWS', 'LAST_INSERT_ID', 'LCASE', 'LOWER', 'UCASE', 'UPPER', 'LPAD', 'RPAD', 'RTRIM', 'LTRIM', 'MD5', 'MINUTE', 'ROUND', 'SECOND', 'SHA1', 'STDDEV', 'STR_TO_DATE', 'WEEK', 'RAND'),
      'chars'     => '/([\\.,!\\(\\)<>:=`]+)/i',
      'constants' => '/(\'[^\']*\'|[0-9]+)/i'
    );

    $sql=str_replace('\\\'','\\&#039;', $sql);

    foreach($color_codes as $key => $code){
      $regexp = in_array( $key, array('constants', 'chars')) ? $words[$key] : '/\\b(' .join("|", $words[$key]) .')\\b/i';
      $sql = preg_replace($regexp, "<span style=\"color:$code\">$1</span>", $sql);
    }

    $sql = str_replace($color_codes, $colors, $sql);
    return '<code style="color:' . Neevo::$highlight_colors['columns'] . '">' . $sql . '</code>' . PHP_EOL;
  }

}
