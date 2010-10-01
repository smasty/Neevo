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

  private  $table, $type, $limit, $offset, $neevo, $resource, $time, $sql, $performed;
  private  $where, $order, $columns, $data = array();

  private static $highlight_colors = array(
    'columns'    => '#00f',
    'chars'      => '#000',
    'keywords'   => '#008000',
    'joins'      => '#555',
    'functions'  => '#008000',
    'constants'  => '#f00'
    );

  public static $sql_functions = array('MIN', 'MAX', 'SUM', 'COUNT', 'AVG', 'CAST', 'COALESCE', 'CHAR_LENGTH', 'LENGTH', 'SUBSTRING', 'DAY', 'MONTH', 'YEAR', 'DATE_FORMAT', 'CRC32', 'CURDATE', 'SYSDATE', 'NOW', 'GETDATE', 'FROM_UNIXTIME', 'FROM_DAYS', 'TO_DAYS', 'HOUR', 'IFNULL', 'ISNULL', 'NVL', 'NVL2', 'INET_ATON', 'INET_NTOA', 'INSTR', 'FOUND_ROWS', 'LAST_INSERT_ID', 'LCASE', 'LOWER', 'UCASE', 'UPPER', 'LPAD', 'RPAD', 'RTRIM', 'LTRIM', 'MD5', 'MINUTE', 'ROUND', 'SECOND', 'SHA1', 'STDDEV', 'STR_TO_DATE', 'WEEK', 'RAND');


  /**
   * Query base constructor
   * @param array $object Reference to instance of Neevo class which initialized Query
   * @param string $type Query type. Possible values: select, insert, update, delete
   * @param string $table Table to interact with
   * @return void
   */
  function  __construct(Neevo $object, $type = '', $table = ''){
    $this->neevo = $object;

    $this->type($type);
    $this->table($table);
  }


  /**
   * Sets execution time of Query
   * @param int $time Time value to set.
   * @return void
   */
  public function setTime($time){
    $this->time = $time;
  }


  /**
   * Returns execution time of Query
   * @return int
   */
  public function time(){
    return $this->time;
  }


  /**
   * Sets table to interact
   * @param string $table
   * @return NeevoQuery fluent interface
   */
  public function table($table){
    $this->table = $table;
    return $this;
  }


  /**
   * Sets query type. Possibe values: select, insert, update, delete
   * @param string $type
   * @return NeevoQuery fluent interface
   */
  public function type($type){
    $this->type = $type;
    return $this;
  }


  /**
   * Method for running direct SQL code
   * @param string $sql Direct SQL code
   * @return NeevoQuery fluent interface
   */
  public function sql($sql){
    $this->sql = $sql;
    $this->type('sql');
    return $this;
  }


  /**
   * Sets columns to retrive in SELECT queries
   * @param array|string $columns Array or comma-separated list of columns.
   * @return NeevoQuery fluent interface
   */
  public function cols($columns){
    if(!is_array($columns)) $columns = explode(',', $columns);
    $this->columns = $columns;
    return $this;
  }


  /**
   * Data for INSERT and UPDATE queries
   * @param array $data Data in format "$column=>$value"
   * @return NeevoQuery fluent interface
   */
  public function data(array $data){
    $this->data = $data;
    return $this;
  }

  
  /**
   * Returns true if query was performed
   * @return bool
   */
  public function performed(){
    return $this->performed;
  }


  /**
   * Returns Neevo instance
   * @return Neevo
   */
  public function neevo(){
    return $this->neevo;
  }


  /**
   * Returns Query resource
   * @return resource
   */
  public function resource(){
    return $this->resource;
  }


  /**
   * Returns table name
   * @return string
   */
  public function getTable(){
    return $this->table;
  }


  /**
   * Returns Query type
   * @return string
   */
  public function getType(){
    return $this->type;
  }


  /**
   * Returns Query LIMIT fraction
   * @return int
   */
  public function getLimit(){
    return $this->limit;
  }


  /**
   * Returns Query OFFSET fraction
   * @return int
   */
  public function getOffset(){
    return $this->offset;
  }


  /**
   * Returns whole query code for direct queries (type=sql)
   * @return string
   */
  public function getSql(){
    return $this->sql;
  }


  /**
   * Returns Query WHERE fraction
   * @return array
   */
  public function getWhere(){
    return $this->where;
  }


  /**
   * Returns Query ORDER BY fraction
   * @return array
   */
  public function getOrder(){
    return $this->order;
  }


  /**
   * Returns Query columns fraction for SELECT queries ([SELECT] col1, col2, ...)
   * @return array
   */
  public function getCols(){
    return $this->columns;
  }


  /**
   * Returns Query values fraction for INSERT/UPDATE queries
   * ([INSERT INTO] (col1,, col2, ...) VALUES (val1, val2, ...) or
   *  [UPDATE tbl] SET col1 = val1,  col2 = val2, ...)
   * @return array
   */
  public function getData(){
    return $this->data;
  }


  /**
   * Sets WHERE condition for Query
   *
   * <p>Supports LIKE and IN functions</p>
   *
   * @param string $where Column to use and optionaly operator/function: "email !=", "email LIKE" or "email IN".
   * @param string|array $value Value to search for: "spam@foo.com", "%@foo.com" or array('john@foo.com', 'doe@foo.com', 'john.doe@foo.com')
   * @param string $glue Glue (AND, OR, etc.) to use betweet this and next WHERE condition. If not set, AND will be used.
   * @return NeevoQuery fluent interface
   */
  public function where($where, $value, $glue = null){
    $where_condition = explode(' ', $where);
    if(is_null($value)){
      $where_condition[1] = "IS";
      $value = "NULL";
    }
    if(is_array($value)) $where_condition[1] = "IN";
    if(!isset($where_condition[1])) $where_condition[1] = '=';
    $column = $where_condition[0];
    $condition = array($column, $where_condition[1], $value, strtoupper($glue ? $glue : "and"));
    $this->where[] = $condition;
    return $this;
  }


  /**
   * Sets ORDER BY rule for Query
   * @param string $args [Infinite arguments] Order rules: "col_name ASC", "col_name" or "col_name DESC", etc...
   * @return NeevoQuery fluent interface
   */
  public function order($args){
    $rules = array();
    $arguments = func_get_args();
    foreach ($arguments as $argument) {
      $order_rule = explode(' ', $argument);
      $rules[] = $order_rule;
    }
    $this->order = $rules;
    return $this;
  }


  /**
   * Sets limit (and offset) for Query
   * @param int $limit Limit
   * @param int $offset Offset
   * @return NeevoQuery fluent interface
   */
  public function limit($limit, $offset = null){
    $this->limit = $limit;
    if(isset($offset) && $this->getType() == 'select') $this->offset = $offset;
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
   * @return resource|NeevoQuery Query resource on SELECT queries or NeevoQuery object
   */
  public function run(){
    $start = explode(" ", microtime());
    $query = $this->neevo()->driver()->query($this->build(), $this->neevo()->connection()->resource());
    if(!$query){
      $this->neevo()->error('Query failed');
      return false;
    }
    else{
      $this->neevo()->incrementQueries();
      $this->neevo()->setLast($this);

      $end = explode(" ", microtime());
      $time = round(max(0, $end[0] - $start[0] + $end[1] - $start[1]), 4);
      $this->setTime($time);

      $this->performed = true;
      if(in_array($this->getType(), array('select', 'sql'))){
        $this->resource = $query;
        return $query;
      }
      else return $this;

    }
  }


  /**
   * Fetches data from given Query resource.
   * @param int $fetch_type Result format. If set to Neevo::MULTIPLE,
   * number of fetched rows is ignored.
   * @return array|string Array of rows represented as associative arrays
   * (column => value), if two or more rows are fetched.<br>
   * Only row as associative array, if only one row is fetched.<br>
   * Numerical array of values from fetched column, if there's only one column
   * fetched in all rows.<br>
   * String, if only one column value is fetched at all.
   */
  public function fetch($fetch_type = null){
    $rows = null;
    if(!in_array($this->getType(), array('select', 'sql'))) $this->neevo()->error('Cannot fetch on this kind of query');

    $resource = $this->performed() ? $this->resource() : $this->run();

    while($tmp_rows = $this->neevo()->driver()->fetch($resource))
      $rows[] = (count($tmp_rows) == 1) ? $tmp_rows[max(array_keys($tmp_rows))] : $tmp_rows;

    $this->neevo()->driver()->free($resource);

    if(count($rows) == 1 && $fetch_type != Neevo::MULTIPLE)
      $rows = $rows[0];

    if(!count($rows) && is_array($rows)) return false; // Empty
    return $resource ? $rows : $this->neevo()->error("Fetching result data failed");
  }


  /**
   * Move internal result pointer
   * @param int $row_number Row number of the new result pointer.
   * @return bool
   */
  public function seek($row_number){
    if(!$this->performed()) $this->run();

    $seek = $this->neevo()->driver()->seek($this->resource(), $row_number);
    return $seek ? $seek : $this->neevo()->error("Cannot seek to row $row_number");
  }


  /**
   * Get the ID generated in the INSERT query
   * @return int
   */
  public function insertId(){
    if(!$this->performed()) $this->run();

    return $this->neevo()->driver()->insertId($this->neevo()->connection()->resource());
  }


  /**
   * Randomize result order. (Shorthand for NeevoQuery->order('RAND()');)
   * @return NeevoQuery fluent interface
   */
  public function rand(){
    $this->neevo()->driver()->rand($this);
    return $this;
  }


  /**
   * Returns number of affected rows for INSERT/UPDATE/DELETE queries and number of rows in result for SELECT queries
   * @return int|FALSE Number of rows (int) or FALSE
   */
  public function rows(){
    if(!$this->performed()) $this->run();

    return $this->neevo()->driver()->rows($this);
  }


  /**
   * Unsets defined parts of Query (WHERE conditions, ORDER BY clauses, affected columns (INSERT, UPDATE), LIMIT, etc.).
   * This method is DEPRECATED and will be removed.
   * 
   * @param string $sql_part Part of Query to unset. Possible values are: (string)
   * <ul>
     * <li>where (for WHERE conditions)</li>
     * <li>order (for ORDER BY clauses)</li>
     * <li>column (for selected columns in SELECT queries)</li>
     * <li>value (for values to put/set in INSERT and UPDATE)</li>
     * <li>limit (for LIMIT clause)</li>
     * <li>offset (for OFFSET clause)</li>
   * </ul>
   * @param int|string|array $position Exact piece of Query part:
   * <ul>
     * <li>int: Ordinal number of Query part piece (WHERE condition, ORDER BY clause, columns in SELECT queries) to unset.</li>
     * <li>string: Column name from defined values (values to put/set in INSERT and UPDATE queries) to unset.</li>
     * <li>array: Array of options (from pevious two) if you want to unset more than one piece of Query part (e.g 2nd and 3rd WHERE condition).</li>
   * </ul>
   * This argument is not required for LIMIT & OFFSET. Default is (int) 1.
   * @return NeevoQuery fluent interface
   */
  public function undo($sql_part, $position = 1){
    if(Neevo::$ignore_deprecated !== true)
      $this->neevo()->error("NeevoQuery::undo() is deprecated and will be removed. To use it, set Neevo::\$ignore_deprecated to TRUE");
    $str = false;
    switch (strtolower($sql_part)) {
      case 'where':
        $part = 'where';
        break;
      case 'order';
        $part = 'order';
        break;
      case 'column';
        $part = 'columns';
        break;
      case 'value';
        $part = 'data';
        break;
      case 'limit':
        $part = 'limit';
        $str = true;
        break;
      case 'offset':
        $part = 'offset';
        $str = true;
        break;
      default:
        $this->neevo()->error("Undo failed: No such Query part '$sql_part' supported for undo()");
        break;
    }

    if($str)
      unset($this->$part);
    else{
      if(isset($this->$part)){
        $positions = array();
        if(!is_array($position)) $positions[] = $position;
        foreach ($positions as $pos) {
          $pos = is_numeric($pos) ? $pos-1 : $pos;
          $apart = $this->$part;
          unset($apart[$pos]);
          foreach($apart as $key=>$value){
            $loop[$key] = $value;
          }
          $this->$part = $loop;
        }
      } else $this->neevo()->error("Undo failed: No such Query part '$sql_part' for this kind of Query");
    }
    $this->performed = null;
    $this->resource = null;
    return $this;
  }


  /**
   * Builds Query from NeevoQuery instance
   * @return string The Query in SQL dialect
   * @access private
   */
  public function build(){

    return $this->neevo()->driver()->build($this);

  }


  /**
   * Returns basic informations about query
   * @param bool $hide_password If set to TRUE (default), password will be replaced by '*****'.
   * @param bool $exclude_connection If set to TRUE (default FALSE), connection info will be excluded.
   * @return array
   */
  public function info($hide_password = true, $exclude_connection = false){
    $info = array(
      'type' => $this->getType(),
      'table' => $this->getTable(),
      'executed' => (bool) $this->performed(),
      'query-string' => $this->dump(false, true)
    );

    if($exclude_connection == true)
      $this->neevo()->connection()->info($hide_password);

    if($this->performed()){
      $info['time'] = $this->time();
      if($this->getType() == 'insert')
        $info['last-insert-id'] = $this->insertId();
    }

    return $info;
  }

  /**
   * Returns name of PRIMARY KEY if defined, NULL otherwise.
   * @return string
   */
  public function getPrimary(){
    $return = null;
    $table = $this->neevo()->driver()->buildTablename($this);
    $q = $this->neevo()->sql('EXPLAIN '. $table);
    foreach($q->fetch(Neevo::MULTIPLE) as $col){
      if($col['Key'] == 'PRI' && !isset($return))
        $return = $col['Field'];
    }
    return $return;
  }


  /*  ******  Internal methods  ******  */


  /**
   * Highlights given SQL code
   * @param string $sql
   * @return string
   */
  private static function _highlightSql($sql){
    $color_codes = array('chars'=>'chars','keywords'=>'kwords','joins'=>'joins','functions'=>'funcs','constants'=>'consts');
    $colors = self::$highlight_colors;
    unset($colors['columns']);

    $words = array(
      'keywords'  => array('SELECT', 'UPDATE', 'INSERT', 'DELETE', 'REPLACE', 'INTO', 'CREATE', 'ALTER', 'TABLE', 'DROP', 'TRUNCATE', 'FROM', 'ADD', 'CHANGE', 'COLUMN', 'KEY', 'WHERE', 'ON', 'CASE', 'WHEN', 'THEN', 'END', 'ELSE', 'AS', 'USING', 'USE', 'INDEX', 'CONSTRAINT', 'REFERENCES', 'DUPLICATE', 'LIMIT', 'OFFSET', 'SET', 'SHOW', 'STATUS', 'BETWEEN', 'AND', 'IS', 'NOT', 'OR', 'XOR', 'INTERVAL', 'TOP', 'GROUP BY', 'ORDER BY', 'DESC', 'ASC', 'COLLATE', 'NAMES', 'UTF8', 'DISTINCT', 'DATABASE', 'CALC_FOUND_ROWS', 'SQL_NO_CACHE', 'MATCH', 'AGAINST', 'LIKE', 'REGEXP', 'RLIKE', 'PRIMARY', 'AUTO_INCREMENT', 'DEFAULT', 'IDENTITY', 'VALUES', 'PROCEDURE', 'FUNCTION', 'TRAN', 'TRANSACTION', 'COMMIT', 'ROLLBACK', 'SAVEPOINT', 'TRIGGER', 'CASCADE', 'DECLARE', 'CURSOR', 'FOR', 'DEALLOCATE'),
      'joins'     => array('JOIN', 'INNER', 'OUTER', 'FULL', 'NATURAL', 'LEFT', 'RIGHT'),
      'functions' => self::$sql_functions,
      'chars'     => '/([\\.,!\\(\\)<>:=`]+)/i',
      'constants' => '/(\'[^\']*\'|[0-9]+)/i'
    );

    $sql=str_replace('\\\'','\\&#039;', $sql);

    foreach($color_codes as $key => $code){
      $regexp = in_array( $key, array('constants', 'chars')) ? $words[$key] : '/\\b(' .join("|", $words[$key]) .')\\b/i';
      $sql = preg_replace($regexp, "<span style=\"color:$code\">$1</span>", $sql);
    }

    $sql = str_replace($color_codes, $colors, $sql);
    return "<code style=\"color:".self::$highlight_colors['columns']."\">$sql</code>\n";
  }

}
