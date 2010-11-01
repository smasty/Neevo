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
 * Neevo SQLite 3 driver (PHP extension 'sqlite')
 *
 * Driver configuration:
 * - database (or file, db, dbname) => database to select
 * - table_prefix (or prefix) => prefix for table names
 * - charset => Character encoding to set (defaults to utf-8)
 * - dbcharset => Database character encoding (will be converted to 'charset')
 * - resource (instance of SQLite3) => Existing SQLite 3 resource
 * 
 * @author Martin Srank
 * @package NeevoDrivers
 */
class NeevoDriverSQLite3 extends NeevoQueryBuilder implements INeevoDriver{

  /** @var Neevo */
  protected $neevo;

  /** @var string */
  private $dbCharset;

  /** @var string */
  private $charset;

  /** @var SQLite3 */
  private $resource;


  /**
   * If driver extension is loaded, sets Neevo reference, otherwise throw exception
   * @param Neevo $neevo
   * @throws NeevoException
   * @return void
   */
  public function  __construct(Neevo $neevo){
    if(!extension_loaded("sqlite3")) throw new NeevoException("PHP extension 'sqlite3' not loaded.");
    $this->neevo = $neevo;
  }


  /**
   * Creates connection to database
   * @param array $config Configuration options
   * @return void
   */
  public function connect(array $config){
    NeevoConnection::alias($config, 'database', 'file');

    if(!isset($config['resource'])) $config['resource'] = null;

    // Connect
    if(!($config['resource'] instanceof SQLite3)){
      try{
        $connection = new SQLite3($config['database']);
      } catch(Exception $e){
          $this->neevo->error($e->getMessage());
      }
    }
    else
      $connection = $config['resource'];

    if(!($connection instanceof SQLite3))
      $this->neevo->error("Opening database file '".$config['database']." failed");
    
    $this->resource = $connection;

    // Set charset
    $this->dbCharset = empty($config['dbcharset']) ? 'UTF-8' : $config['dbcharset'];
    $this->charset = empty($config['charset']) ? 'UTF-8' : $config['charset'];
    if(strcasecmp($this->dbCharset, $this->charset) === 0)
      $this->dbCharset = $this->charset = null;
  }


  /**
   * Closes connection
   * @return void
   */
  public function close(){
    $this->resource->close();
  }


  /**
   * Frees memory used by result
   * @param SQLite3Result $resultSet
   * @return bool
   */
  public function free($resultSet){
    return true;
  }


  /**
   * Executes given SQL query
   * @param string $query_string Query-string.
   * @return SQLite3Result|bool
   */
  public function query($query_string){

    if($this->dbCharset !== null)
      $query_string = iconv($this->charset, $this->dbCharset . '//IGNORE', $query_string);

    return @$this->resource->query($query_string);
  }


  /**
   * Error message with driver-specific additions
   * @param string $neevo_msg Error message
   * @return array Format: array($error_message, $error_number)
   */
  public function error($neevo_msg){
    $no = $this->resource->lastErrorCode();
    $msg = $neevo_msg. '. ' . $this->resource->lastErrorMsg();
    return array($msg, $no);
  }


  /**
   * Fetches row from given Query result set as associative array.
   * @param SQLite3Result $resultSet Result set
   * @return array
   */
  public function fetch($resultSet){
    $row = $resultSet->fetchArray(SQLITE3_ASSOC);
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
   * Fetches all rows from given result set as associative arrays.
   * @param SQLite3Result $resultSet Result set
   * @return array
   */
  public function fetchAll($resultSet){
    throw new NotImplementedException();
  }


  /**
   * Move internal result pointer
   * @param SQLite3Result $resultSet Query resource
   * @param int $row_number Row number of the new result pointer.
   * @return bool
   * @throws NotImplementedException
   */
  public function seek($resultSet, $row_number){
    throw new NotImplementedException();
  }


  /**
   * Get the ID generated in the INSERT query
   * @return int
   */
  public function insertId(){
    return $this->resource->lastInsertRowID();
  }


  /**
   * Randomize result order.
   * @param NeevoResult $query NeevoResult instance
   * @return NeevoResult
   */
  public function rand(NeevoResult $query){
    $query->order('RANDOM()');
  }


  /**
   * Number of rows in result set.
   * @param SQLite3Result $resultSet
   * @return int|FALSE
   * @throws NotImplementedException
   */
  public function rows($resultSet){
    throw new NotImplementedException();
  }


  /**
   * Number of affected rows in previous operation.
   * @return int
   */
  public function affectedRows(){
    return $this->resource->changes();
  }


  /**
   * Name of PRIMARY KEY column for table
   * @param string $table
   * @return void
   * @throws NotImplementedException
   */
  public function getPrimaryKey($table){
    throw new NotImplementedException();
  }


  /**
   * Builds Query from NeevoResult instance
   * @param NeevoResult $query NeevoResult instance
   * @return string the Query
   */
  public function build(NeevoResult $query){

    $where = '';
    $order = '';
    $limit = '';
    $q = '';

    if($query->getSql())
      return $query->getSql().';';

    $table = $query->getTable();

    if($query->getConditions())
      $where = $this->buildWhere($query);

    if($query->getOrdering())
      $order = $this->buildOrder($query);

    if($query->getLimit()) $limit = " LIMIT " .$query->getLimit();
    if($query->getOffset()) $limit .= " OFFSET " .$query->getOffset();

    if($query->getType() == 'select'){
      $cols = $this->buildSelectCols($query);
      $q .= "SELECT $cols FROM $table$where$order$limit";
    }

    elseif($query->getType() == 'insert' && $query->getValues()){
      $insert_data = $this->buildInsertData($query);
      $q .= "INSERT INTO $table$insert_data";
    }

    elseif($query->getType() == 'update' && $query->getValues()){
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
        return "'". $this->resource->escapeString($value) ."'";

      case Neevo::BINARY:
        return "X'" . bin2hex((string) $value) . "'";
      
      case Neevo::DATETIME:
        return ($value instanceof DateTime) ? $value->format("'Y-m-d H:i:s'") : date("'Y-m-d H:i:s'", $value);

      case Neevo::DATE:
        return ($value instanceof DateTime) ? $value->format("'Y-m-d'") : date("'Y-m-d'", $value);
        
      default:
        $this->neevo->error('Unsupported data type');
        break;
    }
  }

}