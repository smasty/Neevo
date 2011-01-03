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
class Neevo{

  /** @var NeevoConnection */
  private $connection;
  
  /** @var INeevoDriver */
  private $driver;

  /** @var INeevoCache */
  private $cache;

  /** @var NeevoStmtBuilder */
  private $stmtBuilder;

  /** @var NeevoResult */
  private $last;

  /** @var int */
  private $queries;

  /** @var bool|callback */
  private $debug;

  
  /** @var bool Ignore warning when using deprecated Neevo methods.*/
  public static $ignoreDeprecated = false;

  /** @var string Default Neevo driver */
  public static $defaultDriver = 'mysql';


  // Neevo revision
  const REVISION = 237;

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
   * @param string $driver Name of driver to use.
   * @param INeevoCache|null $cache Cache to use. NULL for no cache.
   * @return void
   * @throws NeevoException
   */
  public function __construct($driver = null, INeevoCache $cache = null){
    if(!$driver)
      $driver = self::$defaultDriver;
    
    $this->setDriver($driver);
    $this->setCache($cache);
  }


  /**
   * Closes connection to server.
   * @return void
   */
  public function  __destruct(){
    try{
      $this->driver->close();
    } catch(NotImplementedException $e){}
  }


  /**
   * Creates and uses a new connection to a server.
   *
   * Configuration can be different - see the API for your driver.
   * @param array|string|Traversable $config Driver-specific configuration (array, parsable string or traversable object)
   * @return Neevo fluent interface
   */
  public function connect($config){
    $this->setConnection($config);
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
   * Sets Neevo Connection to use
   * @param array|string|Traversable $config
   * @internal
   */
  private function setConnection($config){
    $this->connection = new NeevoConnection($this->driver, $config);
  }


  /**
   * Neevo Driver class
   * @return INeevoDriver
   */
  public function driver(){
    return $this->driver;
  }


  /**
   * Uses given Neevo SQL driver
   * @param string $driver
   * @return Neevo fluent interface
   */
  public function useDriver($driver){
    $this->setDriver($driver);
    return $this;
  }


  /**
   * Sets Neevo driver to use
   * @param string $driver Driver name
   * @throws NeevoException
   * @return void
   * @internal
   */
  private function setDriver($driver){
    $class = "NeevoDriver$driver";

    if(!$this->isDriver($class)){
      @include_once dirname(__FILE__) . '/neevo/drivers/'.strtolower($driver).'.php';

      if(!$this->isDriver($class))
        throw new NeevoException("Unable to create instance of Neevo driver '$driver' - class not found or not matching criteria.");
    }

    $this->driver = new $class($this);

    // Set stmtBuilder
    if(in_array('NeevoStmtBuilder', class_parents($class, false)))
      $this->stmtBuilder = $this->driver;
    else
      $this->stmtBuilder = new NeevoStmtBuilder($this);
  }


  /** @internal */
  private function isDriver($class){
    return (class_exists($class, false) && in_array('INeevoDriver', class_implements($class, false)));
  }


  /**
   * Statement builder class
   * @return NeevoStmtBuilder
   * @internal
   */
  public function stmtBuilder(){
    return $this->stmtBuilder;
  }


  /**
   * Set cache
   * @param INeevoCache $cache
   * @return void
   */
  private function setCache(INeevoCache $cache = null){
    $this->cache = $cache;
  }


  /**
   * Neevo cache object
   * @return INeevoCache|null
   */
  public function cache(){
    return $this->cache;
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
   * Setup debugging mode
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
   * Neevo revision
   * @return int
   */
  public function revision(){
    return self::REVISION;
  }


  /**
   * Alias for revision()
   * @return int
   */
  public function version(){
    return self::REVISION;
  }

  /**
   * Basic information about library
   * @param bool $hide_password Password will be replaced by '*****'.
   * @return array
   */
  public function info($hide_password = true){
    $info = array(
      'executed_queries' => $this->queries(),
      'last_statement' => $this->last()->info($hide_password, true),
      'connection' => $this->connection()->info($hide_password),
      'version' => $this->version(false),
      'error_reporting' => $this->errorReporting()
    );
    return $info;
  }

}


/**
 * Neevo Exception
 * @package Neevo
 */
class NeevoException extends Exception{};


/**
 * Object representing SQL literal value.
 * @package Neevo
 */
class NeevoLiteral {

  /** @var string */
  private $value;

  /**
   * Creates literal value.
   * @param string $value
   */
  public function __construct($value) {
    $this->value = $value;
  }


  /**
   * Literal value
   * @return string
   */
  public function __get($name){
    return $this->value;
  }
  
}



/* Other exceptions */

if(!class_exists('NotImplementedException', false)){
  class NotImplementedException extends Exception{};
}

if(!class_exists('NotSupportedException', false)){
  class NotSupportedException extends Exception{};
}