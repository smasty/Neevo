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
 * Neevo MySQL driver class
 * @package NeevoDrivers
 */
class NeevoDriverMySQL extends NeevoDriver implements INeevoDriver{

  /** @var array $col_quotes Characters used as opening and closing column quote, e.g `column` in MySQL */
  protected $col_quotes = array('`', '`');

  /** @var Neevo $neevo Reference to main Neevo object */
  private $neevo;


  /**
   * If driver extension is loaded, sets Neevo reference, otherwise throw exception
   * @param Neevo $neevo
   * @throws NeevoException
   * @return void
   */
  public function  __construct(Neevo $neevo){
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
    if(!is_resource($connection)) $this->neevo->error("Connection to host '".$opts['host']."' failed");
    if($opts['database']){
      $db = mysql_select_db($opts['database']);
      if(!$db) $this->neevo->error("Could not select database '{$opts['database']}'");
    }

    if($opts['encoding'] && is_resource($connection)){
      if (function_exists('mysql_set_charset'))
				$ok = @mysql_set_charset($opts['encoding'], $connection);
			if (!$ok)
				$this->neevo->sql("SET NAMES ".$opts['encoding'])->run();
    }
    return $connection;
  }


  /**
   * Closes given resource
   * @param resource $resource
   * @return void
   */
  public function close($resource){
    @mysql_close($resource);
  }


  /**
   * Frees memory used by result
   * @param resource $result
   * @return bool
   */
  public function free($result){
    return @mysql_free_result($result);
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
   * Returns error message with driver-specific additions
   * @param string $neevo_msg Error message
   * @return string
   */
  public function error($neevo_msg){
    $mysql_msg = mysql_error();
    $mysql_msg = str_replace('You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use', 'Syntax error', $mysql_msg);

    $msg = $neevo_msg.".";
    if($mysql_msg)
      $msg .= " ".$mysql_msg;

    return $msg;
  }


  /**
   * Fetches row from given Query resource as associative array.
   * @param resource $resource Query resource
   * @return array
   */
  public function fetch($resource){
    return @mysql_fetch_assoc($resource);
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
   * Get the ID generated in the INSERT query
   * @param resource $resource Query resource
   * @return int
   */
  public function insert_id($resource){
    return mysql_insert_id($resource);
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
   * Returns number of affected rows for INSERT/UPDATE/DELETE queries and number
   * of rows in result for SELECT queries
   * @param NeevoQuery $query NeevoQuery instance
   * @return int|FALSE Number of rows (int) or FALSE
   */
  public function rows(NeevoQuery $query){
    if($query->get_type() != 'select')
      $aff_rows = $query->time()
        ? @mysql_affected_rows($query->neevo()->connection()->resource()) : false;
    else $num_rows = @mysql_num_rows($query->resource());

    if($num_rows || $aff_rows)
      return $num_rows ? $num_rows : $aff_rows;
    else return false;
  }


  /**
   * Builds Query from NeevoQuery instance
   * @param NeevoQuery $query NeevoQuery instance
   * @return string The Query
   */
  public function build(NeevoQuery $query){

    $where = "";
    $order = "";
    $limit = "";
    $q = "";

    if($query->get_sql())
      $q = $query->get_sql();

    else{
      $table = $this->build_tablename($query);

      if($query->get_where())
        $where = $this->build_where($query);

      if($query->get_order())
        $order = $this->build_order($query);

      if($query->get_limit()) $limit = " LIMIT " .$query->get_limit();
      if($query->get_offset()) $limit .= " OFFSET " .$query->get_offset();

      if($query->get_type() == 'select'){
        $cols = $this->build_select_cols($query);
        $q .= "SELECT $cols FROM $table$where$order$limit";
      }

      if($query->get_type() == 'insert' && $query->get_data()){
        $insert_data = $this->build_insert_data($query);
        $q .= "INSERT INTO $table$insert_data";
      }

      if($query->get_type() == 'update' && $query->get_data()){
        $update_data = $this->build_update_data($query);
        $q .= "UPDATE $table$update_data$where$order$limit";
      }

      if($query->get_type() == 'delete')
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
   * Returns driver-specific column quotes (opening and closing chars)
   * @return array
   */
  public function get_quotes(){
    return $this->col_quotes;
  }


  /**
   * Return Neevo class instance
   * @return Neevo
   */
  public function neevo(){
    return $this->neevo;
  }

}