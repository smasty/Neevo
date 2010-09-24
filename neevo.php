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

include dirname(__FILE__). "/neevo/NeevoStatic.php";
include dirname(__FILE__). "/neevo/NeevoQuery.php";
include dirname(__FILE__). "/neevo/NeevoDriver.php";
include dirname(__FILE__). "/neevo/NeevoConnection.php";
include dirname(__FILE__). "/neevo/INeevoDriver.php";

include dirname(__FILE__). "/neevo/NeevoDriverMySQL.php";

/**
 * Main Neevo layer class
 * @package Neevo
 */
class Neevo{

  // Fields
  private $connection, $last, $table_prefix, $queries, $error_reporting, $driver, $error_handler;
  private $options = array();

  // Error-reporting levels
  const E_NONE    = 11;
  const E_HANDLE  = 12;
  const E_STRICT  = 13;

  // Neevo version
  const VERSION = "0.3dev";
  const REVISION = 101;

  // Fetch format
  const MULTIPLE = 21;


  /**
   * Neevo
   * @param string $driver Name of driver to use
   * @return void
   */
  public function __construct($driver = false){
    if(!$driver) throw new NeevoException("Driver not set.");
    $this->set_driver($driver);
  }


  /**
   * Closes connection to server.
   * @return void
   */
  public function  __destruct(){
    $this->driver()->close($this->connection()->resource());
  }


  /**
   * Creates and uses a new connection to a server.
   * @param array $opts Array of options in following format:
   * <pre>Array(
   *   driver          =>  driver to use
   *   host            =>  localhost,
   *   username        =>  username,
   *   password        =>  password,
   *   database        =>  database_name,
   *   encoding        =>  utf8,
   *   table_prefix    =>  prefix_
   *   error_reporting =>  error-reporting level; See set_error_reporting() for possible values.
   * );</pre>
   * @return bool
   */
  public function connect(array $opts){
    $connection = $this->create_connection($opts);
    $this->set_connection($connection);
    return (bool) $connection;
  }


  /**
   * Returns current NeevoConnection instance
   * @return NeevoConnection
   */
  public function connection(){
    return $this->connection;
  }


  /**
   * Creates new NeevoConnection instance from given associative array
   * @param array $opts Array of connection options, see Neevo->connect()
   * @see Neevo->connect()
   * @return NeevoConnection
   */
  public function create_connection(array $opts){
    return new NeevoConnection($this, $this->driver(), $opts['username'], $opts['password'], $opts['host'], $opts['database'], $opts['encoding'], $opts['table_prefix']);
  }


  /**
   * Uses given NeevoConnection instance
   * @param NeevoConnection $connection
   * @return Neevo
   */
  public function use_connection(NeevoConnection $connection){
    $this->set_connection($connection);
    return $this;
  }


  /**
   * Sets Neevo Connection to use
   * @param NeevoConnection $connection Instance to use
   */
  public function set_connection(NeevoConnection $connection){
    $this->connection = $connection;
  }


  /**
   * Sets Neevo SQL driver to use
   * @param string $driver Driver name
   * @return void
   * @access private
   */
  private function set_driver($driver){
    if(!$driver) throw new NeevoException("Driver not set.");
    switch (strtolower($driver)) {
      case "mysql":
        $this->driver = new NeevoDriverMySQL($this);
        break;

      default:
        throw new NeevoException("Driver $driver not supported.");
        break;
    }
  }


  /**
   * Uses given Neevo SQL driver
   * @param string $driver
   * @return Neevo
   */
  public function use_driver($driver){
    $this->set_driver($driver);
    return $this;
  }


  /**
   * Returns Neevo Driver class
   * @return INeevoDriver
   */
  public function driver(){
    return $this->driver;
  }


  /**
   * Sets last executed query
   * @param NeevoQuery $last Last executed query
   * @return void
   */
  public function set_last(NeevoQuery $last){
    $this->last = $last;
  }


  /**
   * Returns last executed query
   * @param NeevoQuery $last Last executed query
   * @return NeevoQuery
   */
  public function last(){
    return $this->last;
  }


  /**
   * Increments queries counter
   * @return void
   * @access private
   */
  public function increment_queries(){
    $this->queries++;
  }


  /**
   * Returns amount of executed queries
   * @return int
   */
  public function queries(){
    return $this->queries;
  }


  /**
   * Creates NeevoQuery object for SELECT query
   * @param mixed $columns Array or comma-separated list of columns to select
   * @param string $table Database table to use for selecting
   * @return NeevoQuery
   */
  public function select($columns, $table){
    $q = new NeevoQuery($this, 'select', $table);
    return $q->cols($columns);
  }


  /**
   * Creates NeevoQuery object for INSERT query
   * @param string $table Database table to use for inserting
   * @param array $data Associative array of values to insert in format column => value
   * @return NeevoQuery
   */
  public function insert($table, array $data){
    $q = new NeevoQuery($this, 'insert', $table);
    return $q->data($data);
  }


  /**
   * Creates NeevoQuery object for UPDATE query
   * @param string $table Database table to use for updating
   * @param array $data Associative array of values for update in format column => value
   * @return NeevoQuery
   */
  public function update($table, array $data){
    $q = new NeevoQuery($this, 'update', $table);
    return $q->data($data);
  }


  /**
   * Creates NeevoQuery object for DELETE query
   * @param string $table Database table to use for deleting
   * @return NeevoQuery
   */
  public function delete($table){
    return new NeevoQuery($this, 'delete', $table);
  }


  /**
   * Creates NeevoQuery object for direct SQL query
   * @param string $sql Direct SQL query
   * @return NeevoQuery
   */
  public function sql($sql){
    $q = new NeevoQuery($this);
    return $q->sql($sql);
  }


  /**
   * Sets error-reporting level
   * @param int $value Error-reporting level.
   * Possible values:
   * <ul><li>Neevo::E_NONE: Turns Neevo error-reporting off</li>
   * <li>Neevo::E_HANDLE: Neevo exceptions are sent to defined handler</li>
   * <li>Neevo::E_STRICT: Throws all Neevo exceptions</li></ul>
   * @return void
   */
  public function set_error_reporting($value){
    $this->error_reporting = $value;
    if(!isset($this->error_reporting)) $this->error_reporting = self::E_HANDLE;
  }


  /**
   * Returns error-reporting level
   * @return int
   */
  public function error_reporting(){
    if(!isset($this->error_reporting)) $this->error_reporting = self::E_WARNING;
    return $this->error_reporting;
  }


  /**
   * Sets error-handler function
   * @param string $handler_function Name of error-handler function
   * @return void
   */
  public function set_error_handler($handler_function){
    if(function_exists($handler_function))
      $this->error_handler = $handler_function;
    else $this->error_handler = array('Neevo', 'default_error_handler');
  }


  /**
   * Returns error-handler function name
   * @param string $handler_function Name of error-handler function
   * @return string
   */
  public function error_handler(){
    $func = $this->error_handler;
    if( (is_array($func) && !method_exists($func[0], $func[1]) ) || ( !is_array($func) && !function_exists($func) ) )
      $this->error_handler = array('Neevo', 'default_error_handler');
    return $this->error_handler;
  }


  /**
   * If error_reporting is E_STRICT, throws NeevoException available to catch.
   * Otherwise, sends NeevoException instance to defined handler.
   * @param string $neevo_msg Error message
   * @return false
   * @throws NeevoException
   */
  public function error($neevo_msg){
    $level = $this->error_reporting();

    if($level != Neevo::E_NONE){
      $msg = $this->driver()->error($neevo_msg);
      $exception = new NeevoException($msg);

      if($level == Neevo::E_HANDLE)
        call_user_func ($this->error_handler(), $exception);
      if($level == Neevo::E_STRICT)
        throw $exception;
    }

    return false;
  }


  /**
   * Neevo's default error handler function
   * @param NeevoException $exception
   * return void
   */
  public static function default_error_handler(NeevoException $exception){
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
    $file = basename($path);
    $path = str_replace($file, "<strong>$file</strong>", $path);

    echo "<p><strong>Neevo exception</strong> $act in <em>$path</em> on <strong>line $line</strong>: $message</p>\n";
  }


  /**
   * Returns script memory usage
   * @return string
   */
  public function memory(){
    return NeevoStatic::filesize(memory_get_usage(true));
  }


  /**
   * Returns Neevo version and revision
   * @param bool $string Return as a string, not array
   * @return string|array
   */
  public function version($string = true){
    if($string)
      $return = "Neevo ".self::VERSION." (revision ".self::REVISION.").";
    else
      $return = array(
        'version'  => self::VERSION,
        'revision' => self::REVISION
      );
    return $return;
  }

}


/**
 * Neevo Exception
 * @package Neevo
 */
class NeevoException extends Exception{};
?>
