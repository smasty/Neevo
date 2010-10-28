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
 * @link     http://neevo.smasty.net/
 * @package  Neevo
 *
 */

if(version_compare(PHP_VERSION, '5.1.0', '<')){
  if(version_compare(PHP_VERSION, '5.0.0', '>='))
    throw new Exception('Neevo requires PHP version 5.1.0 or newer');
  if(version_compare(PHP_VERSION, '5.0.0', '<'))
    trigger_error('Neevo requires PHP version 5.1.0 or newer', E_USER_ERROR);
  exit;
}

include_once dirname(__FILE__). '/neevo/NeevoConnection.php';
include_once dirname(__FILE__). '/neevo/INeevoDriver.php';
include_once dirname(__FILE__). '/neevo/NeevoQueryBuilder.php';
include_once dirname(__FILE__). '/neevo/NeevoQuery.php';
include_once dirname(__FILE__). '/neevo/NeevoResult.php';
include_once dirname(__FILE__). '/neevo/NeevoCache.php';

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
  
  /** @var callback */
  private $error_handler;

  /** @var NeevoQuery */
  private $last;

  /** @var int */
  private $queries;

  /** @var int */
  private $error_reporting;

  
  /** @var bool Ignore warning when using deprecated methods.*/
  public static $ignore_deprecated = false;


  // Error-reporting levels
  const E_NONE    = 11;
  const E_HANDLE  = 12;
  const E_STRICT  = 13;

  // Neevo version
  const VERSION = '0.5';
  const REVISION = 173;

  // Data types
  const BOOL = 30;
  const TEXT = 33;
  const BINARY = 34;
  const DATETIME = 36;
  const DATE = 37;


  /**
   * Neevo
   * @param string $driver Name of driver to use.
   * @param INeevoCache|bool $cache Cache to use. NULL for no cache.
   * @return void
   * @throws NeevoException
   */
  public function __construct($driver, $cache = null){
    if(!$driver) throw new NeevoException("Driver not defined.");
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
   * Configuration is different for each driver - see the API for your driver.
   * @param array|string|Traversable $config Driver-specific configuration (array, parsable string or traversable object)
   * @return Neevo fluent interface
   */
  public function connect($config){
    $connection = $this->createConnection($config);
    $this->setConnection($connection);
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
   * Creates new NeevoConnection instance
   *
   * Options for connecting are different for each driver - see an API for your driver.
   * @param array|string|Traversable $config Driver-specific configuration (array, parsable string or traversable object)
   * @return NeevoConnection
   * @internal
   */
  public function createConnection($config){
    try{
      return new NeevoConnection($this->driver, $config);
    }
    catch(NotImplementedException $e){
      throw new NeevoException("Cannot connect: Used driver is not matching criteria.");
    }
  }


  /**
   * Sets Neevo Connection to use
   * @param NeevoConnection $connection Instance to use
   * @internal
   */
  private function setConnection(NeevoConnection $connection){
    $this->connection = $connection;
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
   * @return Neevo
   */
  public function useDriver($driver){
    $this->setDriver($driver);
    return $this;
  }


  /**
   * Sets Neevo SQL driver to use
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
        throw new NeevoException("Unable to create instance of Neevo driver '$driver' - corresponding class not found or not matching criteria.");
    }

    $this->driver = new $class($this);
  }


  /** @internal */
  private function isDriver($class){
    return (class_exists($class, false) &&
            in_array('INeevoDriver', class_implements($class, false)) &&
            in_array('NeevoQueryBuilder', class_parents($class, false))
           );
  }


  /**
   * Sets Neevo cache. If not defined, tries to create cache automatically.
   * @param INeevoCache|FALSE $cache FALSE to disable autocache.
   * @return void
   * @internal
   */
  private function setCache($cache = null){
    // Disable cache.
    if($cache === false | $cache === null)
      return;

    // INeevoCache object passed
    elseif(is_object($cache) && in_array("INeevoCache", class_implements($cache, false)))
      $this->cache = $cache;

    // Not proper value passed
    else
      throw new NeevoException('Argument 2 passed to Neevo::__construct() must be boolean or implement interface INeevoCache');

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
      return $this->cache()->load($key);
  }


  /**
   * Save data
   * @param string $key
   * @param mixed $value
   * @return void
   */
  public function cacheSave($key, $value){
    if(isset($this->cache))
      $this->cache()->save($key, $value);
  }


  /**
   * Last executed query
   * @param NeevoQuery $last Last executed query
   * @return NeevoQuery
   */
  public function last(){
    return $this->last;
  }


  /**
   * Sets last executed query
   * @param NeevoQuery $last Last executed query
   * @return void
   * @internal
   */
  public function setLast(NeevoQuery $last){
    $this->last = $last;
  }


  /**
   * Amount of executed queries
   * @return int
   */
  public function queries(){
    return $this->queries;
  }


  /**
   * Increments queries counter
   * @return void
   * @internal
   */
  public function incrementQueries(){
    $this->queries++;
  }


  /**
   * Creates SELECT query
   * @param string|array $cols Columns to select (array or comma-separated list)
   * @param string $table Table name
   * @return NeevoQuery fluent interface
   */
  public function select($columns = '*', $table){
    $q = new NeevoQuery($this);
    return $q->select($columns, $table);
  }


  /**
   * Creates INSERT query
   * @param string $table Table name
   * @param array $values Values to insert
   * @return NeevoQuery fluent interface
   */
  public function insert($table, array $values){
    $q = new NeevoQuery($this);
    return $q->insert($table, $values);
  }


  /**
   * Alias for Neevo::insert().
   * @return NeevoQuery fluent interface
   */
  public function insertInto($table, array $values){
    return $this->insert($table, $values);
  }


  /**
   * Creates UPDATE query
   * @param string $table Table name
   * @param array $data Data to update
   * @return NeevoQuery fluent interface
   */
  public function update($table, array $data){
    $q = new NeevoQuery($this);
    return $q->update($table, $data);
  }


  /**
   * Creates DELETE query
   * @param string $table Table name
   * @return NeevoQuery fluent interface
   */
  public function delete($table){
    $q = new NeevoQuery($this);
    return $q->delete($table);
  }


  /**
   * Creates query with direct SQL
   * @param string $sql SQL code
   * @return NeevoQuery fluent interface
   */
  public function sql($sql){
    $q = new NeevoQuery($this);
    return $q->sql($sql);
  }


  /**
   * Error-reporting level
   * @return int
   */
  public function errorReporting(){
    if(!isset($this->error_reporting)) $this->error_reporting = self::E_STRICT;
    return $this->error_reporting;
  }


  /**
   * Sets error-reporting level
   *
   * Possible values:
   * - Neevo::E_NONE: Turns Neevo error-reporting off
   * - Neevo::E_HANDLE: Neevo exceptions are sent to defined handler
   * - Neevo::E_STRICT: Throws all Neevo exceptions (default)
   * @param int $value Error-reporting level.
   * @return void
   */
  public function setErrorReporting($value){
    $this->error_reporting = $value;
    if(!isset($this->error_reporting)) $this->error_reporting = self::E_STRICT;
  }


  /**
   * Error-handler function name
   * @param string $handler_function Name of error-handler function
   * @return string
   */
  public function errorHandler(){
    $func = $this->error_handler;
    if( (is_array($func) && !method_exists($func[0], $func[1]) ) || ( !is_array($func) && !function_exists($func) ) )
      $this->error_handler = array('Neevo', 'defaultErrorHandler');
    return $this->error_handler;
  }


  /**
   * Sets error-handler function
   * @param callback $callback Name of error-handler function
   * @return void
   */
  public function setErrorHandler($callback){
    if(function_exists($callback))
      $this->error_handler = $callback;
    else $this->error_handler = array('Neevo', 'defaultErrorHandler');
  }


  /**
   * If error_reporting is E_STRICT, throws NeevoException available to catch.
   * Sends NeevoException instance to defined handler if E_HANDLE, does nothing if E_NONE.
   * @param string $neevo_msg Error message
   * @return false
   * @throws NeevoException
   */
  public function error($neevo_msg){
    $level = $this->errorReporting();

    if($level !== Neevo::E_NONE){
      try{
        $err = $this->driver->error($neevo_msg);
      }
      catch(NotImplementedException $e){
        $err = $neevo_msg;
      }
      $exception = new NeevoException($err[0], $err[1]);

      if($level === Neevo::E_STRICT)
        throw $exception;
      elseif($level === Neevo::E_HANDLE)
        call_user_func($this->errorHandler(), $exception);
    }

    return false;
  }


  /**
   * Neevo's default error handler function
   * @param NeevoException $exception
   * @return void
   * @internal
   */
  public static function defaultErrorHandler(NeevoException $exception){
    $message = $exception->getMessage();
    $trace = $exception->getTrace();
    if(!empty($trace)){
      $last = $trace[count($trace)-1];
      $line = $last['line'];
      $path = $last['file'];
      $act = "occured";
    }
    else{
      $line = $exception->getLine();
      $path = $exception->getFile();
      $act = "thrown";
    }

    $code = is_numeric($exception->getCode()) ? ' #'.$exception->getCode() : '';
    $file = basename($path);
    $path = str_replace($file, "<strong>$file</strong>", $path);

    echo "<p><strong>Neevo exception$code</strong> $act in <em>$path</em> on <strong>line $line</strong>: $message</p>\n";
  }


  /**
   * Neevo version and revision
   * @param bool $string Return as a string, not array
   * @return string|array
   */
  public function version($string = true){
    if($string)
      $return = 'Neevo '.self::VERSION.' (revision '.self::REVISION.').';
    else
      $return = array(
        'version'  => self::VERSION,
        'revision' => self::REVISION
      );
    return $return;
  }


  /**
   * Basic information about library
   * @param bool $hide_password Password will be replaced by '*****'.
   * @return array
   */
  public function info($hide_password = true){
    $info = array(
      'executed_queries' => $this->queries(),
      'last_query' => $this->last()->info($hide_password, true),
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

if(!class_exists('NotImplementedException', false)){
  class NotImplementedException extends Exception{};
}

/**
 * Class for object representing SQL literal value.
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
