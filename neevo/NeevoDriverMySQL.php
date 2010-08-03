<?php
/**
 * Neevo - Tiny open-source database abstraction layer for PHP
 *
 * Copyright 2010 Martin Srank (http://smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * @copyright  Copyright (c) 2010 Martin Srank (http://smasty.net)
 * @license    http://www.opensource.org/licenses/mit-license.php  MIT license
 * @link       http://labs.smasty.net/neevo/
 * @package    Neevo
 * @version    0.02dev
 *
 */

/**
 * Neevo MySQL driver class
 * @package Neevo
 */
class NeevoDriverMySQL implements INeevoDriver{

  /** Character used as column quote, e.g `column` in MySQL */
  const COL_QUOTE = '`';
  /** Character ussed to escape quotes in queries, e.g. \ in MySQL */
  const ESCAPE_CHAR  = "\\";

  /** @var Neevo $neevo Reference to main Neevo object */
  private $neevo;


  /**
   * If driver extension is loaded, sets Neevo reference, otherwise throw exception
   * @param Neevo $neevo
   * @throws NeevoException
   */
  public function  __construct($neevo){
    if(!extension_loaded("mysql")) throw new NeevoException("PHP extension 'mysql' not loaded.");
    $this->neevo = $neevo;
  }


  /**
   * Connects to database server, selects database and sets encoding (if defined)
   * @param array $opts Array of options in following format:
   * <pre>Array(
   *   host            =>  localhost,
   *   username        =>  username,
   *   password        =>  password,
   *   database        =>  database_name,
   *   encoding        =>  utf8
   * );</pre>
   * @return bool
   */
  public function connect(array $opts){
    $connection = @mysql_connect($opts['host'], $opts['username'], $opts['password']);
    if(!is_resource($connection) or !$this->test_connection($connection)) $this->neevo->error("Connection to host '".$opts['host']."' failed");
    if($opts['database']){
      $db = mysql_select_db($opts['database']);
      if(!$db) $this->neevo->error("Could not select database '{$opts['database']}'");
    }
    $this->neevo->set_resource($connection);
    $this->neevo->set_options($opts);

    if($opts['encoding']){
      try{
        $e = mysql_query("SET NAMES ".$opts['encoding']);
      }
      catch (NeevoException $e){}
    }
    return (bool) $connection;
  }


  /**
   * Closes given resource
   * @param resource $resource
   */
  public function close($resource){
    @mysql_close($resource);
  }


  /**
   * Executes given SQL query
   * @param string $query_string Query-string.
   * @param resource Connection resource
   * @return resource
   */
  public function query($query_string, $resource){
    return @mysql_query($query_string, $resource);
  }


   /**
   * If error_reporting is turned on, throws NeevoException available to catch.
   * @param string $neevo_msg Error message
    * @param bool $catch Catch this error or not
   * @throws NeevoException
   * @return false
   */
  public function error($neevo_msg, $catch){
    $mysql_msg = mysql_error();
    $no = mysql_errno();
    $mysql_msg = str_replace('You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use', 'Syntax error', $mysql_msg);
    $msg = "$neevo_msg. $mysql_msg";
    $mode = $this->neevo->error_reporting();
    if($mode != Neevo::E_NONE){
      if(($mode != Neevo::E_STRICT && $catch) || $mode == Neevo::E_CATCH){
        try{
          throw new NeevoException($msg);
        } catch (NeevoException $e){
          echo "<b>Catched NeevoException:</b> ".$e->getMessage()."\n";
        }
      }
      else throw new NeevoException($msg);
    }
  }


  /**
   * Fetches data from given Query resource
   * @param resource $resource Query resource
   * @return mixed Array or string (if only one value is returned) or FALSE (if nothing is returned).
   */
  public function fetch($resource){
    while($tmp_rows = @mysql_fetch_assoc($resource))
      $rows[] = (count($tmp_rows) == 1) ? $tmp_rows[max(array_keys($tmp_rows))] : $tmp_rows;

    if(count($rows) == 1)
      $rows = $rows[0];

    if(!count($rows) && is_array($rows)) return false; // Empty

    mysql_free_result($resource);
    return $rows;
  }


  /**
   * Move internal result pointer
   * @param resource $resource Query resource
   * @param int $row_number Row number of the new result pointer.
   * @return bool
   */
  public function seek($resource, $row_number){
    return @mysql_data_seek($resource, $row_number);
  }


  /**
   * Randomize result order.
   * @param NeevoQuery $query NeevoQuery instance
   * @return NeevoQuery
   */
  public function rand(NeevoQuery $query){
    $query->order('RAND()');
  }


  /**
   * Returns number of affected rows for INSERT/UPDATE/DELETE queries and number of rows in result for SELECT queries
   * @param NeevoQuery $query NeevoQuery instance
   * @param bool $string Return rows as a string ("Rows: 5", "Affected: 10"). Default: FALSE
   * @return mixed Number of rows (int) or FALSE
   */
  public function rows(NeevoQuery $query, $string){
    if($query->type!='select') $aff_rows = $query->time() ? @mysql_affected_rows($query->neevo->resource()) : false;
    else $num_rows = @mysql_num_rows($query->resource);

    if($num_rows || $aff_rows){
      if($string){
        return $num_rows ? "Rows: $num_rows" : "Affected: $aff_rows";
      }
      else return $num_rows ? $num_rows : $aff_rows;
    }
    else return false;
  }


  /**
   * Builds Query from NeevoQuery instance
   * @param NeevoQuery $query NeevoQuery instance
   * @return string the Query
   */
  public function build(NeevoQuery $query){

    if($query->sql)
      $q = $query->sql;

    else{
      $table = $this->build_tablename($query);

      if($query->where)
        $where = $this->build_where($query);

      if($query->order)
        $order = $this->build_order($query);

      if($query->limit) $limit = " LIMIT " .$query->limit;
      if($query->offset) $limit .= " OFFSET " .$query->offset;

      if($query->type == 'select'){
        $cols = $this->build_select_cols($query);
        $q .= "SELECT $cols FROM $table$where$order$limit";
      }

      if($query->type == 'insert' && $query->data){
        $insert_data = $this->build_insert_data($query);
        $q .= "INSERT INTO $table$insert_data";
      }

      if($query->type == 'update' && $query->data){
        $update_data = $this->build_update_data($query);
        $q .= "UPDATE $table$update_data$where$order$limit";
      }

      if($query->type == 'delete')
        $q .= "DELETE FROM $table$where$order$limit";
    }
    return "$q;";
  }


  /**
   * Escapes given string for use in SQL
   * @param string $string
   * @return string
   */
  public function escape_string($string){
    return mysql_real_escape_string($string);
  }


  /**
   * Builds table-name for queries
   * @param NeevoQuery $query NeevoQuery instance
   * @return string
   */
  private function build_tablename(NeevoQuery $query){
    $pieces = explode(".", $query->table);
    $prefix = $query->neevo->prefix();
    if($pieces[1])
      return self::COL_QUOTE .$pieces[0] .self::COL_QUOTE ."." .self::COL_QUOTE .$prefix .$pieces[1] .self::COL_QUOTE;
    else return self::COL_QUOTE .$prefix .$pieces[0] .self::COL_QUOTE;
  }


  /**
   * Builds WHERE statement for queries
   * @param NeevoQuery $query NeevoQuery instance
   * @return string
   */
  private function build_where(NeevoQuery $query){
    foreach ($query->where as $where) {
      if(empty($where[3])) $where[3] = 'AND';
      if(is_array($where[2])){
        $where[2] = "(" .join(", ", NeevoStatic::escape_array($where[2], $this->neevo)) .")";
        $in_construct = true;
      }
      $wheres[] = $where;
    }
    unset($wheres[count($wheres)-1][3]);
    foreach ($wheres as $in_where) {
      $in_where[0] = (NeevoStatic::is_sql_func($in_where[0])) ? NeevoStatic::quote_sql_func($in_where[0]) : self::COL_QUOTE .$in_where[0] .self::COL_QUOTE;
      if(!$in_construct) $in_where[2] = NeevoStatic::escape_string($in_where[2], $this->neevo);
      $wheres2[] = join(' ', $in_where);
    }
    foreach ($wheres2 as &$rplc_where){
      $rplc_where = str_replace(array(' = ', ' != '), array('=', '!='), $rplc_where);
    }
    return " WHERE ".join(' ', $wheres2);
  }


  /**
   * Builds data part for INSERT queries ([INSERT INTO] (...) VALUES (...) )
   * @param NeevoQuery $query NeevoQuery instance
   * @return string
   */
  private function build_insert_data(NeevoQuery $query){
    foreach(NeevoStatic::escape_array($query->data, $this->neevo) as $col => $value){
      $cols[] = self::COL_QUOTE .$col .self::COL_QUOTE;
      $values[] = $value;
    }
    return " (".join(', ',$cols).") VALUES (".join(', ',$values).")";
  }


  /**
   * Builds data part for UPDATE queries ([UPDATE ...] SET ...)
   * @param NeevoQuery $query NeevoQuery instance
   * @return string
   */
  private function build_update_data(NeevoQuery $query){
    foreach(NeevoStatic::escape_array($query->data, $this->neevo) as $col => $value){
      $update[] = self::COL_QUOTE .$col .self::COL_QUOTE ."=" .$value;
    }
    return " SET " .join(', ', $update);
  }


  /**
   * Builds ORDER BY statement for queries
   * @param NeevoQuery $query NeevoQuery instance
   * @return string
   */
  private function build_order(NeevoQuery $query){
    foreach ($query->order as $in_order) {
      $in_order[0] = (NeevoStatic::is_sql_func($in_order[0])) ? $in_order[0] : self::COL_QUOTE .$in_order[0] .self::COL_QUOTE;
      $orders[] = join(' ', $in_order);
    }
    return " ORDER BY ".join(', ', $orders);
  }


  /**
   * Builds columns part for SELECT queries
   * @param NeevoQuery $query NeevoQuery instance
   * @return string
   */
  private function build_select_cols(NeevoQuery $query){
    foreach ($query->columns as $col) {
      $col = trim($col);
      if($col != '*'){
        if(NeevoStatic::is_as_constr($col)) $col = NeevoStatic::quote_as_constr($col, self::COL_QUOTE);
        elseif(NeevoStatic::is_sql_func($col)) $col = NeevoStatic::quote_sql_func($col);
        else $col = self::COL_QUOTE .$col .self::COL_QUOTE;
      }
      $cols[] = $col;
    }
    return join(', ', $cols);
  }
  

  /**
   * Tests if MySQL connection is usable (wrong username without password)
   * @param resource $resource
   * @return bool
   */
  private function test_connection($resource){
    try{
      $q = mysql_query("SHOW DATABASES", $resource);
      $r = $this->fetch($q);
      if(!$r || $r == "information_schema") throw new NeevoException("Error!");
    }
    catch (NeevoException $e){return false;}
    return true;
  }
}

?>