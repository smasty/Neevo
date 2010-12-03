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
 * Neevo MySQLi driver (PHP extension 'mysqli')
 *
 * Driver configuration:
 * - host (or hostname, server) => MySQL server name or address
 * - port => MySQL server port
 * - socket
 * - username (or user)
 * - password (or pass, pswd)
 * - database (or db, dbname) => database to select
 * - table_prefix (or prefix) => prefix for table names
 * - charset => Character encoding to set (defaults to utf8)
 * - resource (instance of mysqli) => Existing MySQLi connection
 *
 * @author Martin Srank
 * @package NeevoDrivers
 */
class NeevoDriverMySQLi implements INeevoDriver{

  /** @var Neevo */
  private $neevo;

  /** @var mysqli */
  private $resource;


  /**
   * If driver extension is loaded, sets Neevo reference, otherwise throw exception
   * @param Neevo
   * @throws NeevoException
   * @return void
   */
  public function  __construct(Neevo $neevo){
    if(!extension_loaded("mysqli")) throw new NeevoException("PHP extension 'mysqli' not loaded.");
    $this->neevo = $neevo;
  }


  /**
   * Creates connection to database
   * @param array Configuration options
   * @return void
   */
  public function connect(array $config){

    // Defaults
    if(!isset($config['resource'])) $config['resource'] = null;
    if(!isset($config['charset'])) $config['charset'] = 'utf8';
    if(!isset($config['username'])) $config['username'] = ini_get('mysqli.default_user');
    if(!isset($config['password'])) $config['password'] = ini_get('mysqli.default_pw');
    if(!isset($config['socket'])) $config['socket'] = ini_get('mysqli.default_socket');
    if(!isset($config['port'])) $config['port'] = null;
    if(!isset($config['host'])){
      $host = ini_get('mysqli.default_host');
      if($host){
        $config['host'] = $host;
        $config['port'] = ini_get('mysqli.default_port');
      } else $config['host'] = $config['port'] = null;
    }

    // Connect
    if(!($config['resource']) instanceof mysqli)
      $this->resource = new mysqli($config['host'], $config['username'], $config['password'], $config['database'], $config['port'], $config['socket']);
    else
      $this->resource = $config['resource'];

    if($this->resource->connect_errno){
      $this->neevo->error($this->resource->connect_error);
    }

    // Set charset
    if($this->resource instanceof mysqli){
      $ok = @$this->resource->set_charset($config['charset']);

      if(!$ok) $this->query("SET NAMES ".$config['charset']);
    }

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
   * @param mysqli_result
   * @return bool
   */
  public function free($resultSet){}


  /**
   * Executes given SQL query
   * @param string Query-string.
   * @return mysqli_result|bool
   */
  public function query($query_string){
    return @$this->resource->query($query_string);
  }


  /**
   * Error message with driver-specific additions
   * @param string Error message
   * @return array Format: array($error_message, $error_number)
   */
  public function error($neevo_msg){
    $mysql_msg = $this->resource->error;
    $mysql_msg = str_replace('You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use', 'Syntax error', $mysql_msg);

    $msg = $neevo_msg.".";
    if($mysql_msg)
      $msg .= " ".$mysql_msg;

    return array($msg, $this->resource->errno);
  }


  /**
   * Fetches row from given Query result set as associative array.
   * @param mysqli_result Result set
   * @return array
   */
  public function fetch($resultSet){
    return @$resultSet->fetch_assoc();
  }


  /**
   * Fetches all rows from given result set as associative arrays.
   * @param mysqli_result Result set
   * @return array
   */
  public function fetchAll($resultSet){
    return @$resultSet->fetch_all(MYSQLI_ASSOC);
  }


  /**
   * Move internal result pointer
   * @param mysqli_result Query resource
   * @param int Row number of the new result pointer.
   * @return bool
   */
  public function seek($resultSet, $row_number){
    return @$resultSet->data_seek($row_number);
  }


  /**
   * Get the ID generated in the INSERT query
   * @return int
   */
  public function insertId(){
    return $this->resource->insert_id;
  }


  /**
   * Randomize result order.
   * @param NeevoResult NeevoResult instance
   * @return NeevoResult
   */
  public function rand(NeevoResult $query){
    $query->order('RAND()');
  }


  /**
   * Number of rows in result set.
   * @param mysqli_result
   * @return int|FALSE
   */
  public function rows($resultSet){
    if($resultSet instanceof mysqli_result)
      return $resultSet->num_rows;
    return false;
  }


  /**
   * Number of affected rows in previous operation.
   * @return int
   */
  public function affectedRows(){
    return $this->resource->affected_rows;
  }


  /**
   * Name of PRIMARY KEY column for table
   * @param string
   * @return string|null
   */
  public function getPrimaryKey($table){
    $return = null;
    $q = $this->query('SHOW FULL COLUMNS FROM '. $table);
    $arr = $this->fetchAll($q);
    foreach($arr as $col){
      if($col['Key'] === 'PRI' && !isset($return))
        $return = $col['Field'];
    }
    return $return;
  }


  /**
   * Escapes given value
   * @param mixed
   * @param int Type of value (Neevo::TEXT, Neevo::BOOL...)
   * @return mixed
   */
  public function escape($value, $type){
    switch($type){
      case Neevo::BOOL:
        return $value ? 1 :0;

      case Neevo::TEXT:
        return "'". $this->resource->real_escape_string($value) ."'";
        break;

      case Neevo::DATETIME:
        return ($value instanceof DateTime) ? $value->format("'Y-m-d H:i:s'") : date("'Y-m-d H:i:s'", $value);
        
      default:
        $this->neevo->error('Unsupported data type');
        break;
    }
  }

}