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

// PHP compatibility
if(version_compare(PHP_VERSION, '5.1.0', '<'))
  trigger_error('Neevo requires PHP version 5.1.0 or newer', E_USER_ERROR);

include_once dirname(__FILE__). '/neevo/NeevoConnection.php';
include_once dirname(__FILE__). '/neevo/NeevoStmtBase.php';
include_once dirname(__FILE__). '/neevo/NeevoStmtBuilder.php';
include_once dirname(__FILE__). '/neevo/NeevoResult.php';
include_once dirname(__FILE__). '/neevo/NeevoStmt.php';
include_once dirname(__FILE__). '/neevo/NeevoRow.php';
include_once dirname(__FILE__). '/neevo/NeevoCache.php';
include_once dirname(__FILE__). '/neevo/INeevoDriver.php';

/**
 * Main Neevo layer class.
 * @package Neevo
 */
class Neevo extends NeevoAbstract {

  /** @var NeevoResult */
  private $last;

  /** @var int */
  private $queries;

  
  /** @var bool Ignore warning when using deprecated Neevo methods.*/
  public static $ignoreDeprecated = false;

  /** @var string Default Neevo driver */
  public static $defaultDriver = 'mysql';


  // Neevo revision
  const REVISION = 245;

  // Data types
  const BOOL = 30;
  const TEXT = 33;
  const DATETIME = 36;

  // Fetch formats
  const OBJECT = 1;
  const ASSOC = 2;

  // Statement types
  const STMT_SELECT = 'stmt_select';
  const STMT_INSERT = 'stmt_insert';
  const STMT_UPDATE = 'stmt_update';
  const STMT_DELETE = 'stmt_delete';

  // JOIN types
  const JOIN_LEFT = 'join_left';
  const JOIN_RIGHT = 'join_right';
  const JOIN_INNER = 'join_inner';

  /**
   * Instantiate Neevo.
   *
   * Configuration can be different - see the API for your driver.
   * @param array|string|Traversable $config Driver-specific configuration.
   * @param INeevoCache|null $cache Cache to use. NULL for no cache.
   * @return void
   * @throws NeevoException
   */
  public function __construct($config, INeevoCache $cache = null){

    // Backward compatibility with REV < 238
    if(is_string($config)){
      parse_str($config, $arr);
      if(!reset($arr)) // 1st item empty = driver only
        $this->_old_driver = $config;
      else $this->connect($config);
    }
    else $this->connect($config);
    $this->cache = $cache;
  }


  /**
   * Closes connection to server.
   * @return void
   */
  public function  __destruct(){
    try{
      $this->driver()->close();
    } catch(NotImplementedException $e){}
  }


  /**
   * Creates and uses a new connection to a server.
   *
   * Configuration can be different - see the API for your driver.
   * @param array|string|Traversable $config Driver-specific configuration.
   * @return Neevo fluent interface
   */
  public function connect($config){
    $this->connection = new NeevoConnection(
      $config,
      $this,
      isset($this->_old_driver) ? $this->_old_driver : null
    );
    return $this;
  }


  /**
   * Current NeevoConnection instance
   * @return NeevoConnection
   */
  public function connection(){
    return $this->connection;
  }


  /**
   * Neevo Driver class
   * @return INeevoDriver
   */
  public function driver(){
    return $this->connection->driver;
  }


  /**
   * Statement builder class
   * @return NeevoStmtBuilder
   * @internal
   */
  public function stmtBuilder(){
    return $this->connection->stmtBuilder;
  }


  /**
   * Load stored data
   * @param string $key
   * @return mixed|null null if not found
   */
  public function cacheLoad($key){
    if(isset($this->cache))
      return $this->cache->load($key);
    return null;
  }


  /**
   * Save data
   * @param string $key
   * @param mixed $value
   * @return void
   */
  public function cacheSave($key, $value){
    if(isset($this->cache))
      $this->cache->save($key, $value);
  }


  /**
   * Last executed statement info
   * @return array
   */
  public function last(){
    return $this->last;
  }


  /**
   * Sets last executed statement
   * @param array $last Last executed statement
   * @return void
   * @internal
   */
  public function setLast(array $last){
    $this->queries++;
    $this->last = $last;
    $this->logQuery($last);
  }


  /** @internal */
  private function logQuery(array $query){
    if($this->debug){
      if(!is_callable($this->debug))
        fwrite(STDERR, '-- ['.($query['time'] * 1000).'ms] '."$query[query_string]\n");
      else
        call_user_func($this->debug, $query['query_string'], $query['time'], $query);
    }
  }


  /**
   * Setup debug mode
   * @param bool|callback $debug TRUE for STD_ERR, FALSE to disable.
   * @throws InvalidArgumentException
   * @return void
   */
  public function debug($debug = true){
    if(is_bool($debug) || is_callable($debug))
      $this->debug = $debug;
    else
      throw new InvalidArgumentException('Argument 1 passed to '.__METHOD__.' must be a valid callback or boolean.');
  }


  /**
   * Amount of executed queries
   * @return int
   */
  public function queries(){
    return $this->queries;
  }


  /**
   * SELECT statement factory.
   * @param string|array $columns Columns to select (array or comma-separated list)
   * @param string $table Table name
   * @return NeevoResult fluent interface
   */
  public function select($columns = null, $table = null){
    return new NeevoResult($this, $columns, $table);
  }


  /**
   * INSERT statement factory.
   * @param string $table Table name
   * @param array $values Values to insert
   * @return NeevoStmt fluent interface
   */
  public function insert($table, array $values){
    $q = new NeevoStmt($this);
    return $q->insert($table, $values);
  }


  /**
   * Alias for Neevo::insert().
   * @return NeevoStmt fluent interface
   */
  public function insertInto($table, array $values){
    return $this->insert($table, $values);
  }


  /**
   * UPDATE statement factory.
   * @param string $table Table name
   * @param array $data Data to update
   * @return NeevoStmt fluent interface
   */
  public function update($table, array $data){
    $q = new NeevoStmt($this);
    return $q->update($table, $data);
  }


  /**
   * DELETE statement factory.
   * @param string $table Table name
   * @return NeevoStmt fluent interface
   */
  public function delete($table){
    $q = new NeevoStmt($this);
    return $q->delete($table);
  }


  /**
   * Basic information about library
   * @param bool $hide_password Password will be replaced by '*****'.
   * @return array
   */
  public function info($hide_password = true){
    $info = array(
      'executed' => $this->queries(),
      'last' => $this->last()->info($hide_password, true),
      'connection' => $this->connection->info($hide_password),
      'version' => $this->revision(false)
    );
    return $info;
  }


  /**
   * Neevo revision
   * @return int
   */
  public function revision(){
    return self::REVISION;
  }

  /** @internal */
  public function version(){
    return self::REVISION;
  }

}


/**
 * Friend visibility emulation class
 * @package Neevo
 */
abstract class NeevoAbstract{
  /** @var Neevo */
  protected $neevo;
  
  /** @var NeevoConnection */
  protected $connection;

  /** @var bool|callback */
  protected $debug;
}


/**
 * Object representing SQL literal value.
 * @package Neevo
 */
class NeevoLiteral {
  private $value;
  public function __construct($value) {
    $this->value = $value;
  }
  public function __get($name){
    return $this->value;
  }
}


/**
 * Neevo Exception
 * @package Neevo
 */
class NeevoException extends Exception{};


/* Other exceptions */

if(!class_exists('NotImplementedException', false)){
  class NotImplementedException extends Exception{};
}

if(!class_exists('NotSupportedException', false)){
  class NotSupportedException extends Exception{};
}