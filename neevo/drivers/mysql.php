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
 * Neevo MySQL driver (PHP extension 'mysql')
 *
 * Driver configuration:
 *  - host => MySQL server name or address
 *  - port (int) => MySQL server port
 *  - username
 *  - password
 *  - database => database to select
 *  - charset => Character encoding to set (defaults to utf8)
 *  - persistent (bool) => Try to find a persistent link
 *  - unbuffered (bool) => Sends query without fetching and buffering the result
 *
 *  - resource (type resource) => Existing MySQL link
 *  - lazy, table_prefix... => see NeevoConnection
 *
 * @author Martin Srank
 * @package NeevoDrivers
 */
class NeevoDriverMySQL implements INeevoDriver{

  private $resource, $unbuffered, $affectedRows;

  /**
   * Check for required PHP extension.
   * @throws NeevoException
   * @return void
   */
  public function  __construct(Neevo $neevo){
    if(!extension_loaded("mysql")){
      throw new NeevoException("PHP extension 'mysql' not loaded.");
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
      'username' => ini_get('mysql.default_user'),
      'password' => ini_get('mysql.default_password'),
      'host' => ini_get('mysql.default_host'),
      'port' => ini_get('mysql.default_port'),
      'persistent' => false,
      'unbuffered' => false
    );

    $config += $defaults;

    if(isset($config['port'])){
      $host = $config['host'] .':'. $config['port'];
    }
    else $host = $config['host'];

    // Connect
    if(is_resource($config['resource'])){
      $connection = $config['resource'];
    }
    elseif($config['persistent']){
      $connection = @mysql_pconnect($host, $config['username'], $config['password']);
    }
    else{
      $connection = @mysql_connect($host, $config['username'], $config['password']);
    }

    if(!is_resource($connection)){
      throw new NeevoException("Connection to host '$host' failed.");
    }

    // Select DB
    if($config['database']){
      $db = mysql_select_db($config['database']);
      if(!$db){
        throw new NeevoException("Could not select database '$config[database]'.");
      }
    }

    $this->resource = $connection;

    //Set charset
    if(is_resource($connection)){
      if(function_exists('mysql_set_charset')){
        @mysql_set_charset($config['charset'], $connection);
      }
      else{
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
    @mysql_close($this->resource);
  }


  /**
   * Free memory used by given result set.
   * @param resource $resultSet
   * @return bool
   */
  public function free($resultSet){
    return @mysql_free_result($resultSet);
  }


  /**
   * Execute given SQL statement.
   * @param string $queryString Query-string.
   * @throws NeevoException
   * @return resource|bool
   */
  public function query($queryString){

    $this->affectedRows = false;
    if($this->unbuffered){
      $result = @mysql_unbuffered_query($queryString, $this->resource);
    } else{
      $result = @mysql_query($queryString, $this->resource);
    }

    $error = str_replace('You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use', 'Syntax error', @mysql_error($this->resource));
    if($error && $result === false){
      throw new NeevoException("Query failed. $error", @mysql_errno($this->resource));
    }

    $this->affectedRows = @mysql_affected_rows($this->resource);
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
   * @param resource $resultSet Result set
   * @return array
   */
  public function fetch($resultSet){
    return @mysql_fetch_assoc($resultSet);
  }


  /**
   * Move internal result pointer.
   * @param resource $resultSet
   * @param int $offset
   * @return bool
   * @throws NotSupportedException
   */
  public function seek($resultSet, $offset){
    if($this->unbuffered){
      throw new NotSupportedException('Cannot seek on unbuffered result.');
    }
    return @mysql_data_seek($resultSet, $offset);
  }


  /**
   * Get the ID generated in the INSERT statement.
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
   * Get the number of rows in the given result set.
   * @param resource $resultSet
   * @return int|FALSE
   * @throws NotSupportedException
   */
  public function rows($resultSet){
    if($this->unbuffered){
      throw new NotSupportedException('Cannot count rows on unbuffered result.');
    }
    return @mysql_num_rows($resultSet);
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
   * @param int $type Type of value (Neevo::TEXT, Neevo::BOOL...)
   * @throws InvalidArgumentException
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
        throw new InvalidArgumentException('Unsupported data type.');
        break;
    }
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

}