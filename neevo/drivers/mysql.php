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
 * Neevo MySQL driver (PHP extension 'mysql')
 *
 * Driver configuration:
 * - host (or hostname, server) => MySQL server name or address
 * - port => MySQL server port
 * - username (or user)
 * - password (or pass, pswd)
 * - database (or db, dbname) => database to select
 * - table_prefix (or prefix) => prefix for table names
 * - charset => Character encoding to set (defaults to utf8)
 * - resource (type resource) => Existing MySQL connection (created by mysql_connect)
 *
 * @author Martin Srank
 * @package NeevoDrivers
 */
class NeevoDriverMySQL implements INeevoDriver{

  /** @var Neevo */
  private $neevo;

  /** @var resource */
  private $resource;


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
   * Creates connection to database
   * @param array $config Configuration options
   * @return void
   */
  public function connect(array $config){

    // Defaults
    if(!isset($config['resource'])) $config['resource'] = null;
    if(!isset($config['charset'])) $config['charset'] = 'utf8';
    if(!isset($config['username'])) $config['username'] = ini_get('mysql.default_user');
    if(!isset($config['password'])) $config['password'] = ini_get('mysql.default_password');
    if(!isset($config['host'])){
      $host = ini_get('mysql.default_host');
      if($host){
        $config['host'] = $host;
        $config['port'] = ini_get('mysql.default_port');
      }
      else $config['host'] = null;
    }

    if(isset($config['port']))
      $host = $config['host'] .':'. $config['port'];
    else $host = $config['host'];

    // Connect
    if(!is_resource($config['resource']))
      $connection = @mysql_connect($host, $config['username'], $config['password']);
    else
      $connection = $config['resource'];

    if(!is_resource($connection))
      $this->neevo->error("Connection to host '".$config['host']."' failed");

    // Select DB
    if($config['database']){
      $db = mysql_select_db($config['database']);
      if(!$db) $this->neevo->error("Could not select database '{$config['database']}'");
    }

    $this->resource = $connection;

    //Set charset
    if(is_resource($connection)){
      if(function_exists('mysql_set_charset'))
				$ok = @mysql_set_charset($config['charset'], $connection);

      if(!$ok) $this->query("SET NAMES ".$config['charset']);
    }
  }


  /**
   * Closes connection
   * @return void
   */
  public function close(){
    @mysql_close($this->resource);
  }


  /**
   * Frees memory used by result
   * @param resource $resultSet
   * @return bool
   */
  public function free($resultSet){
    return @mysql_free_result($resultSet);
  }


  /**
   * Executes given SQL statement
   * @param string $queryString Query-string.
   * @return resource|bool
   */
  public function query($queryString){
    return @mysql_query($queryString, $this->resource);
  }


  /**
   * Error message with driver-specific additions
   * @param string $message Error message
   * @return array Format: array($error_message, $error_number)
   */
  public function error($message){
    $mysql_msg = @mysql_error($this->resource);
    $mysql_msg = str_replace('You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use', 'Syntax error', $mysql_msg);

    $msg = $message.".";
    if($mysql_msg)
      $msg .= " ".$mysql_msg;

    return array($msg, @mysql_errno($this->resource));
  }


  /**
   * Fetches row from given result set as associative array.
   * @param resource $resultSet Result set
   * @return array
   */
  public function fetch($resultSet){
    return @mysql_fetch_assoc($resultSet);
  }


  /**
   * Fetches all rows from given result set as associative arrays.
   * @param resource $resultSet Result set
   * @throws NotImplementedException
   */
  public function fetchAll($resultSet){
    throw new NotImplementedException();
  }


  /**
   * Move internal result pointer
   * @param resource $resultSet
   * @param int $offset
   * @return bool
   */
  public function seek($resultSet, $offset){
    return @mysql_data_seek($resultSet, $offset);
  }


  /**
   * Get the ID generated in the INSERT statement
   * @return int
   */
  public function insertId(){
    return @mysql_insert_id($this->resource);
  }


  /**
   * Randomize result order.
   * @param NeevoStmtBase $statement
   * @return void
   */
  public function rand(NeevoStmtBase $statement){
    $statement->order('RAND()');
  }


  /**
   * Number of rows in result set.
   * @param resource $resultSet
   * @return int|FALSE
   */
  public function rows($resultSet){
    return @mysql_num_rows($resultSet);
  }


  /**
   * Number of affected rows in previous operation.
   * @return int
   */
  public function affectedRows(){
    return @mysql_affected_rows($this->resource);
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
        return "'". mysql_real_escape_string($value, $this->resource) ."'";
        break;

      case Neevo::DATETIME:
        return ($value instanceof DateTime) ? $value->format("'Y-m-d H:i:s'") : date("'Y-m-d H:i:s'", $value);
        
      default:
        $this->neevo->error('Unsupported data type');
        break;
    }
  }

}