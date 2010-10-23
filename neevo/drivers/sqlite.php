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
 * Neevo SQLite driver (PHP extension 'sqlite')
 *
 * Driver connect options:
 *  - database (or file, db, dbname) => database to select
 *  - charset => Character encoding to set (defaults to utf-8)
 *  - dbcharset => Database character encoding (will be converted to 'charset')
 *
 * @package NeevoDrivers
 */
class NeevoDriverSQLite extends NeevoDriver implements INeevoDriver{

  private $neevo, $resource, $last_error, $dbCharset, $charset;


  /**
   * If driver extension is loaded, sets Neevo reference, otherwise throw exception
   * @param Neevo $neevo
   * @throws NeevoException
   * @return void
   */
  public function  __construct(Neevo $neevo){
    if(!extension_loaded("sqlite")) throw new NeevoException("PHP extension 'sqlite' not loaded.");
    $this->neevo = $neevo;
  }


  /**
   * Creates connection to database
   * @param array $opts Array of connection options
   * @return void
   */
  public function connect(array $opts){
    NeevoConnection::alias($opts, 'database', 'file');

    // Connect
    $connection = sqlite_open($opts['database'], 0666, $error);
    if(!is_resource($connection))
      $this->neevo()->error("Connection to database '".$opts['database']." failed");
    $this->resource = $connection;

    // Set charset
    $this->dbCharset = empty($opts['dbcharset']) ? 'UTF-8' : $opts['dbcharset'];
    $this->charset = empty($opts['charset']) ? 'UTF-8' : $opts['charset'];
    if(strcasecmp($this->dbCharset, $this->charset) === 0)
      $this->dbCharset = $this->charset = null;
  }


  /**
   * Closes connection
   * @return void
   */
  public function close(){
    @sqlite_close($this->resource);
  }


  /**
   * Frees memory used by result
   * @param resource $resultSet
   * @return bool
   */
  public function free($resultSet){
    return true;
  }


  /**
   * Executes given SQL query
   * @param string $query_string Query-string.
   * @return resource|bool
   */
  public function query($query_string){

    if($this->dbCharset !== null)
      $query_string = iconv($this->charset, $this->dbCharset . '//IGNORE', $query_string);

    $this->last_error = '';
    $q = sqlite_query($this->resource, $query_string, null, $error);
    $this->last_error = $error;
    return $q;
  }


  /**
   * Error message with driver-specific additions
   * @param string $neevo_msg Error message
   * @return array Format: array($error_message, $error_number)
   */
  public function error($neevo_msg){
    $no = sqlite_last_error($this->resource);
    $msg = $neevo_msg. '. ' . ucfirst($this->last_error);
    return array($msg, $no);
  }


  /**
   * Fetches row from given Query resource as associative array.
   * @param resource $resultSet Query resource
   * @return array
   */
  public function fetch($resultSet){
    $row = @sqlite_fetch_array($resultSet, SQLITE_ASSOC);
    $charset = $this->charset === null ? null : $this->charset.'//TRANSLIT';
    if($row && $charset){
      $fields = array();
      foreach($row as $key=>$val){
        if($charset !== null && is_string($val))
          $val = iconv($this->dbcharset, $charset, $val);
        $fields[str_replace(array('[', ']'), '', $key)] = $val;
      }
      return $fileds;
    }
    return $row;
  }


  /**
   * Move internal result pointer
   * @param resource $resultSet Query resource
   * @param int $row_number Row number of the new result pointer.
   * @return bool
   */
  public function seek($resultSet, $row_number){
    return @sqlite_seek($resultSet, $row_number);
  }


  /**
   * Get the ID generated in the INSERT query
   * @return int
   */
  public function insertId(){
    return @sqlite_last_insert_rowid($this->resource);
  }


  /**
   * Randomize result order.
   * @param NeevoQuery $query NeevoQuery instance
   * @return NeevoQuery
   */
  public function rand(NeevoQuery $query){
    $query->order('RANDOM()');
  }


  /**
   * Number of rows in result set.
   * @param resource $resultSet
   * @return int|FALSE
   */
  public function rows($resultSet){
    return @sqlite_num_rows($resultSet);
  }


  /**
   * Number of affected rows in previous operation.
   * @return int
   */
  public function affectedRows(){
    return @sqlite_changes($this->resource);
  }


  /**
   * Name of PRIMARY KEY column for table
   * @param string $table
   * @return null
   * @throws NotImplementedException
   */
  public function getPrimaryKey($table){
    throw new NotImplementedException();
    return null;
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
      $q .= "UPDATE $table$update_data$where";
    }

    elseif($query->getType() == 'delete')
      $q .= "DELETE FROM $table$where";

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
      case Neevo::BINARY:
        return "'". sqlite_escape_string($value) ."'";
      
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