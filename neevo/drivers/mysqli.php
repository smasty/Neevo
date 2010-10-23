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
 * Neevo MySQLi driver (PHP extension 'mysqli')
 *
 * Driver connect options:
 * - host => MySQL server name or address
 * - port => MySQL server port
 * - socket
 * - username (or user)
 * - password (or pass, pswd)
 * - database (or db, dbname) => database to select
 * - charset => Character encoding to set (defaults to utf8)
 *
 * @author Martin Srank
 * @package NeevoDrivers
 */
class NeevoDriverMySQLi extends NeevoQueryBuilder implements INeevoDriver{

  private $neevo, $resource;


  /**
   * If driver extension is loaded, sets Neevo reference, otherwise throw exception
   * @param Neevo $neevo
   * @throws NeevoException
   * @return void
   */
  public function  __construct(Neevo $neevo){
    if(!extension_loaded("mysqli")) throw new NeevoException("PHP extension 'mysqli' not loaded.");
    $this->neevo = $neevo;
  }


  /**
   * Creates connection to database
   * @param array $opts Array of connection options
   * @return void
   */
  public function connect(array $opts){

    // Defaults
    if(!isset($opts['charset'])) $opts['encodng'] = 'utf8';
    if(!isset($opts['username'])) $opts['username'] = ini_get('mysqli.default_user');
    if(!isset($opts['password'])) $opts['password'] = ini_get('mysqli.default_pw');
    if(!isset($opts['socket'])) $opts['socket'] = ini_get('mysqli.default_socket');
    if(!isset($opts['port'])) $opts['port'] = null;
    if(!isset($opts['host'])){
      $host = ini_get('mysqli.default_host');
      if($host){
        $opts['host'] = $host;
        $opts['port'] = ini_get('mysqli.default_port');
      } else $opts['host'] = $opts['port'] = null;
    }

    // Connect
    $this->resource = @mysqli_connect($opts['host'], $opts['username'], $opts['password'], $opts['database'], $opts['port'], $opts['socket']);

    if(mysqli_connect_errno()){
      $this->neevo()->error(mysqli_connect_error());
    }

    // Set charset
    if($opts['charset'] && $this->resource instanceof MySQLi){
      if(function_exists('mysqli_set_charset'))
				$ok = @mysqli_set_charset($this->resource, $opts['charset']);

      if(!$ok) $this->query("SET NAMES ".$opts['charset']);
    }

  }


  /**
   * Closes connection
   * @return void
   */
  public function close(){
    @mysqli_close($this->resource);
  }


  /**
   * Frees memory used by result
   * @param mysqli_result $resultSet
   * @return bool
   */
  public function free($resultSet){
    return @mysqli_free_result($resultSet);
  }


  /**
   * Executes given SQL query
   * @param string $query_string Query-string.
   * @return mysqli_result|bool
   */
  public function query($query_string){
    return @mysqli_query($this->resource, $query_string);
  }


  /**
   * Error message with driver-specific additions
   * @param string $neevo_msg Error message
   * @return array Format: array($error_message, $error_number)
   */
  public function error($neevo_msg){
    $mysql_msg = @mysqli_error($this->resource);
    $mysql_msg = str_replace('You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use', 'Syntax error', $mysql_msg);

    $msg = $neevo_msg.".";
    if($mysql_msg)
      $msg .= " ".$mysql_msg;

    return array($msg, @mysqli_errno($this->resource));
  }


  /**
   * Fetches row from given Query resource as associative array.
   * @param mysqli_result $resultSet Query resource
   * @return array
   */
  public function fetch($resultSet){
    return @mysqli_fetch_assoc($resultSet);
  }


  /**
   * Move internal result pointer
   * @param mysqli_result $resultSet Query resource
   * @param int $row_number Row number of the new result pointer.
   * @return bool
   */
  public function seek($resultSet, $row_number){
    return @mysqli_data_seek($resultSet, $row_number);
  }


  /**
   * Get the ID generated in the INSERT query
   * @return int
   */
  public function insertId(){
    return @mysqli_insert_id($this->resource);
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
   * Number of rows in result set.
   * @param mysqli_result $resultSet
   * @return int|FALSE
   */
  public function rows($resultSet){
    return @mysqli_num_rows($resultSet);
  }


  /**
   * Number of affected rows in previous operation.
   * @return int
   */
  public function affectedRows(){
    return @mysqli_affected_rows($this->resource);
  }


  /**
   * Name of PRIMARY KEY column for table
   * @param string $table
   * @return string|null
   */
  public function getPrimaryKey($table){
    $return = null;
    $arr = array();
    $q = $this->query('SHOW FULL COLUMNS FROM '. $table);
    while($row = $this->fetch($q))
      $arr[] = $row;
    foreach($arr as $col){
      if($col['Key'] === 'PRI' && !isset($return))
        $return = $col['Field'];
    }
    return $return;
  }


  /**
   * Builds Query from NeevoQuery instance
   * @param NeevoQuery $query NeevoQuery instance
   * @return string the Query
   */
  public function build(NeevoQuery $query){

    $where = '';
    $order = '';
    $limit = '';
    $q = '';

    if($query->getSql())
      return $query->getSql().';';

    $table = $query->getTable();

    if($query->getWhere())
      $where = $this->buildWhere($query);

    if($query->getOrder())
      $order = $this->buildOrder($query);

    if($query->getLimit()) $limit = " LIMIT " .$query->getLimit();
    if($query->getOffset()) $limit .= " OFFSET " .$query->getOffset();

    if($query->getType() == 'select'){
      $cols = $this->buildSelectCols($query);
      $q .= "SELECT $cols FROM $table$where$order$limit";
    }

    elseif($query->getType() == 'insert' && $query->getData()){
      $insert_data = $this->buildInsertData($query);
      $q .= "INSERT INTO $table$insert_data";
    }

    elseif($query->getType() == 'update' && $query->getData()){
      $update_data = $this->buildUpdateData($query);
      $q .= "UPDATE $table$update_data$where$order$limit";
    }

    elseif($query->getType() == 'delete')
      $q .= "DELETE FROM $table$where$order$limit";

    return $q.';';
  }


  /**
   * Escapes given value
   * @param mixed $value
   * @param int $type Type of value (Neevo::TEXT, Neevo::BOOL...)
   * @return mixed
   */
  public function escape($value, $type){
    switch($type){
      case Neevo::BOOL:
        return $value ? 1 :0;

      case Neevo::TEXT:
        return "'". mysqli_real_escape_string($this->resource, $value) ."'";
        break;

      case Neevo::BINARY:
        return "_binary'". mysqli_real_escape_string($this->resource, $value) ."'";

      case Neevo::DATETIME:
        return ($value instanceof DateTime) ? $value->format("'Y-m-d H:i:s'") : date("'Y-m-d H:i:s'", $value);

      case Neevo::DATE:
        return ($value instanceof DateTime) ? $value->format("'Y-m-d'") : date("'Y-m-d'", $value);
        
      default:
        $this->neevo()->error('Unsupported data type');
        break;
    }
  }


  /**
   * Return Neevo class instance
   * @return Neevo
   */
  public function neevo(){
    return $this->neevo;
  }

}