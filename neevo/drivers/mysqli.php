<?php
/**
 * Neevo - Tiny open-source database abstraction layer for PHP
 *
 * Copyright 2010-2011 Martin Srank (http://smasty.net)
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
 *  - host => MySQL server name or address
 *  - port (int) => MySQL server port
 *  - socket
 *  - username
 *  - password
 *  - database => database to select
 *  - charset => Character encoding to set (defaults to utf8)
 *  - peristent (bool) => Try to find a persistent link
 *  - unbuffered (bool) => Sends query without fetching and buffering the result
 *
 *  - resource (instance of mysqli) => Existing MySQLi link
 *  - lazy, table_prefix... => see NeevoConnection
 *
 * @author Martin Srank
 * @package NeevoDrivers
 */
class NeevoDriverMySQLi implements INeevoDriver{

  private $resource, $unbuffered, $affectedRows;

  /**
   * Check for required PHP extension.
   * @throws NeevoException
   * @return void
   */
  public function  __construct(){
    if(!extension_loaded("mysqli")){
      throw new NeevoException("PHP extension 'mysqli' not loaded.");
    }
  }

  /**
   * Create connection to database.
   * @param array $config Configuration options
   * @throws NeevoException
   * @return void
   */
  public function connect(array $config){

    // Defaults
    $defaults = array(
      'resource' => null,
      'charset' => 'utf8',
      'username' => ini_get('mysqli.default_user'),
      'password' => ini_get('mysqli.default_pw'),
      'socket' => ini_get('mysqli.default_socket'),
      'port' => ini_get('mysqli.default_port'),
      'host' => ini_get('mysqli.default_host'),
      'persistent' => false,
      'unbuffered' => false
    );

    $config += $defaults;

    // Connect
    if($config['resource'] instanceof mysqli){
      $this->resource = $config['resource'];
    }
    else{
      $this->resource = new mysqli($config['host'], $config['username'], $config['password'], $config['database'], $config['port'], $config['socket']);
    }

    if($this->resource->connect_errno){
      throw new NeevoException($this->resource->connect_error, $this->resource->connect_errno);
    }

    // Set charset
    if($this->resource instanceof mysqli){
      $ok = @$this->resource->set_charset($config['charset']);
      if(!$ok){
        $this->query("SET NAMES ".$config['charset']);
      }
    }

    $this->unbuffered = $config['unbuffered'];
  }

  /**
   * Close the connection.
   * @return void
   */
  public function close(){
    @$this->resource->close();
  }

  /**
   * Free memory used by given result set.
   * @param mysqli_result $resultSet
   * @return bool
   */
  public function free($resultSet){
    return true;
  }

  /**
   * Execute given SQL statement.
   * @param string $queryString Query-string.
   * @return mysqli_result|bool
   * @throws NeevoException
   */
  public function query($queryString){

    $this->affectedRows = false;
    $result = $this->resource->query($queryString, $this->unbuffered ? MYSQLI_USE_RESULT : MYSQLI_STORE_RESULT);

    $error = str_replace('You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use', 'Syntax error', $this->resource->error);
    if($error && $result === false){
      throw new NeevoException("Query failed. $error", $this->resource->errno, $queryString);
    }

    $this->affectedRows = $this->resource->affected_rows;
    return $result;
  }

  /**
   * Begin a transaction if supported.
   * @param string $savepoint
   * @return void
   */
  public function begin($savepoint = null){
    $this->query($savepoint ? "SAVEPOINT $savepoint" : 'START TRANSACTION');
  }

  /**
   * Commit statements in a transaction.
   * @param string $savepoint
   * @return void
   */
  public function commit($savepoint = null){
    $this->query($savepoint ? "RELEASE SAVEPOINT $savepoint" : 'COMMIT');
  }

  /**
   * Rollback changes in a transaction.
   * @param string $savepoint
   * @return void
   */
  public function rollback($savepoint = null){
    $this->query($savepoint ? "ROLLBACK TO SAVEPOINT $savepoint" : 'ROLLBACK');
  }

  /**
   * Fetch row from given result set as an associative array.
   * @param mysqli_result $resultSet Result set
   * @return array
   */
  public function fetch($resultSet){
    return $resultSet->fetch_assoc();
  }

  /**
   * Move internal result pointer.
   * @param mysqli_result $resultSet
   * @param int $offset
   * @return bool
   * @throws NeevoDriverException
   */
  public function seek($resultSet, $offset){
    if($this->unbuffered){
      throw new NeevoDriverException('Cannot seek on unbuffered result.');
    }
    return $resultSet->data_seek($offset);
  }

  /**
   * Get the ID generated in the INSERT statement.
   * @return int
   */
  public function insertId(){
    return $this->resource->insert_id;
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
   * Get the number of rows in the given result set.
   * @param mysqli_result $resultSet
   * @return int|FALSE
   * @throws NeevoDriverException
   */
  public function rows($resultSet){
    if($this->unbuffered){
      throw new NeevoDriverException('Cannot seek on unbuffered result.');
    }
    if($resultSet instanceof mysqli_result){
      return $resultSet->num_rows;
    }
    return false;
  }

  /**
   * Get the number of affected rows in previous operation.
   * @return int
   */
  public function affectedRows(){
    return $this->affectedRows;
  }

  /**
   * Escape given value.
   * @param mixed $value
   * @param string $type Type of value (Neevo::TEXT, Neevo::BOOL...)
   * @throws InvalidArgumentException
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
        throw new InvalidArgumentException('Unsupported data type.');
        break;
    }
  }

  /**
   * Decode given value.
   * @param mixed $value
   * @param string $type
   * @return mixed
   */
  public function unescape($value, $type){
    if($type === Neevo::BINARY){
      return $value;
    }
    throw new InvalidArgumentException('Unsupported data type.');
  }

  /**
   * Get the PRIMARY KEY column for given table.
   * @param $table string
   * @return string
   */
  public function getPrimaryKey($table){
    $key = '';
    $q = $this->query('SHOW FULL COLUMNS FROM '.$table);
    while($col = $this->fetch($q)){
      if(strtolower($col['Key']) === 'pri' && $key === ''){
        $key = $col['Field'];
      }
    }
    return $key;
  }

  /**
   * Get types of columns in given result set.
   * @param mysqli_result $resultSet
   * @param string $table
   * @return array
   */
  public function getColumnTypes($resultSet, $table){
    static $colTypes;
    if(empty($colTypes)){
      $constants = get_defined_constants(true);
      foreach($constants['mysqli'] as $type => $code){
        if(strncmp($type, 'MYSQLI_TYPE_', 12) === 0){
          $colTypes[$code] = strtolower(substr($type, 12));
        }
      }
      $colTypes[MYSQLI_TYPE_LONG] = $colTypes[MYSQLI_TYPE_SHORT] = $colTypes[MYSQLI_TYPE_TINY] = 'int';
    }

    $cols = array();
    while($field = $resultSet->fetch_field()){
      $cols[$field->name] = $colTypes[$field->type];
    }
    return $cols;
  }

}