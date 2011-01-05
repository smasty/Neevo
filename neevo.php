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
if(version_compare(PHP_VERSION, '5.1.0', '<')){
  trigger_error('Neevo requires PHP version 5.1.0 or newer', E_USER_ERROR);
}
include_once dirname(__FILE__). '/neevo/NeevoConnection.php';
include_once dirname(__FILE__). '/neevo/NeevoStmtBase.php';
include_once dirname(__FILE__). '/neevo/NeevoStmtBuilder.php';
include_once dirname(__FILE__). '/neevo/NeevoResult.php';
include_once dirname(__FILE__). '/neevo/NeevoStmt.php';
include_once dirname(__FILE__). '/neevo/NeevoRow.php';
include_once dirname(__FILE__). '/neevo/NeevoCache.php';
include_once dirname(__FILE__). '/neevo/INeevoDriver.php';

/**
 * Core Neevo class.
 * @package Neevo
 */
class Neevo extends NeevoAbstract implements SplSubject {

  private $last, $queries, $observers;
  
  /** @var bool Ignore warning when using deprecated Neevo methods.*/
  public static $ignoreDeprecated = false;

  /** @var string Default Neevo driver */
  public static $defaultDriver = 'mysql';

  // Neevo revision
  const REVISION = 263;

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
   * Configure Neevo and establish a connection.
   *
   * Configuration can be different - see the API for your driver.
   * @param mixed $config Connection configuration.
   * @param INeevoCache|null $cache Cache to use. NULL for no cache.
   * @return void
   * @throws NeevoException
   */
  public function __construct($config, INeevoCache $cache = null){

    // Backward compatibility with REV < 238
    if(is_string($config)){
      parse_str($config, $arr);
      if(!reset($arr)){ // 1st item empty = driver only
        $this->_old_driver = $config;
      }
      else{
        $this->connect($config);
      }
    }
    else{
      $this->connect($config);
    }
    $this->cache = $cache;
    $this->observers = new SplObjectStorage;
  }


  /**
   * Close connection to server.
   * @return void
   */
  public function  __destruct(){
    try{
      $this->driver()->close();
    } catch(NotImplementedException $e){}
  }


  /**
   * Establish a new connection.
   *
   * Configuration can be different - see the API for your driver.
   * @param mixed $config Connection configuration.
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
   * Current NeevoConnection instance.
   * @return NeevoConnection
   */
  public function connection(){
    return $this->connection;
  }


  /**
   * Current Neevo Driver instance.
   * @return INeevoDriver
   */
  public function driver(){
    return $this->connection->driver;
  }


  /**
   * Current StmtBuilder instance.
   * @return NeevoStmtBuilder
   * @internal
   */
  public function stmtBuilder(){
    return $this->connection->stmtBuilder;
  }


  /**
   * Fetch stored data.
   * @param string $key
   * @return mixed|null null if not found
   */
  public function cacheFetch($key){
    if($this->cache instanceof INeevoCache){
      return $this->cache->get($key);
    }
    return null;
  }


  /**
   * Store data in cache.
   * @param string $key
   * @param mixed $value
   * @return void
   */
  public function cacheStore($key, $value){
    if($this->cache instanceof INeevoCache){
      $this->cache->set($key, $value);
    }
  }


  /**
   * Last executed statement info.
   * @return array
   */
  public function last(){
    return $this->last;
  }


  /**
   * Set last executed statement.
   * @internal
   */
  public function setLast(array $last){
    $this->queries++;
    $this->last = $last;
    $this->notify();
  }


  /**
   * Get amount of executed queries.
   * @return int
   */
  public function queries(){
    return $this->queries;
  }


  /**
   * Attach an observer for debugging.
   * @param SplObserver $observer
   */
  public function attach(SplObserver $observer){
    $this->observers->attach($observer);
  }


  /**
   * Detach given observer.
   * @param SplObserver $observer
   */
  public function detach(SplObserver $observer){
    if($this->observers->contains($observer)){
      $this->observers->detach($observer);
    }
  }


  /**
   * Notify observers.
   */
  public function notify(){
    foreach($this->observers as $observer){
      $observer->update($this);
    }
  }

    /**
   * SELECT statement factory.
   * @param string|array $columns array or comma-separated list
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
   * Begin a transaction if supported.
   * @param string $savepoint
   * @return void
   */
  public function begin($savepoint = null){
    $this->driver()->begin($savepoint);
  }


  /**
   * Commit statements in a transaction.
   * @param string $savepoint
   * @return void
   */
  public function commit($savepoint = null){
    $this->driver()->commit($savepoint);
  }


  /**
   * Rollback changes in a transaction.
   * @param string $savepoint
   * @return void
   */
  public function rollback($savepoint = null){
    $this->driver()->rollback($savepoint);
  }


  /**
   * Basic information about the library.
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
   * Neevo revision.
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
 * Friend visibility emulation class.
 * @package Neevo
 */
abstract class NeevoAbstract{

  /** @var Neevo */
  protected $neevo;
  
  /** @var NeevoConnection */
  protected $connection;
}


/**
 * Representation of SQL literal.
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
 * Neevo Exception.
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