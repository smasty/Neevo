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
include dirname(__FILE__). "/neevo/INeevoDriver.php";

include dirname(__FILE__). "/neevo/NeevoDriverMySQL.php";

/**
 * Main Neevo layer class
 * @package Neevo
 */
class Neevo{

  // Fields
  private $resource, $last, $table_prefix, $queries, $error_reporting, $driver, $error_handler;
  private $options = array();

  // Error-reporting levels
  const E_NONE    = 1;
  const E_CATCH   = 2;
  const E_WARNING = 3;
  const E_STRICT  = 4;

  // Neevo version
  const VERSION = "0.2dev";
  const REVISION = 81;


  /**
   * Neevo
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
   * @see Neevo::set_error_reporting(), Neevo::set_prefix();
   * @return void
   */
  public function __construct(array $opts){
    $this->set_driver($opts['driver']);
    $this->connect($opts);
    if($opts['error_reporting']) $this->error_reporting = $opts['error_reporting'];
    if($opts['table_prefix']) $this->table_prefix = $opts['table_prefix'];
  }


  /**
   * Closes connection to server.
   * @return void
   */
  public function  __destruct(){
    $this->driver()->close($this->resource);
  }


  /**
   * Connects to database server, selects database and sets encoding (if defined)
   * @param array $opts
   * @return bool
   */
  private function connect(array $opts){
    return $this->driver()->connect($opts);
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
   * Returns Neevo SQL driver
   * @return INeevoDriver
   */
  public function driver(){
    return $this->driver;
  }


  /**
   * Sets connection resource
   * @param resource $resource
   * @return void
   * @access private
   */
  public function set_resource($resource){
    $this->resource = $resource;
  }


  /**
   * Returns resource identifier
   * @return resource
   */
  public function resource(){
    return $this->resource;
  }


  /**
   * Sets connection options
   * @param array $opts
   * @return void
   * @access private
   */
  public function set_options(array $opts){
    $this->options = $opts;
  }


  /**
   * Sets table prefix
   * @param string $prefix Table prefix to set
   * @return void
   */
  public function set_prefix($prefix){
    $this->table_prefix = $prefix;
  }


  /**
   * Returns table prefix
   * @return string
   */
  public function prefix(){
    return $this->table_prefix;
  }


  /**
   * Sets error-reporting level
   * @param int $value Error-reporting level.
   * Possible values:
   * <ul><li>Neevo::E_NONE: Turns Neevo error-reporting off</li>
   * <li>Neevo::E_CATCH: Catches all Neevo exceptions by defined handler</li>
   * <li>Neevo::E_WARNING: Catches only Neevo warnings</li>
   * <li>Neevo::E_STRICT: Catches no Neevo exceptions</li></ul>
   * @return void
   */
  public function set_error_reporting($value){
    $this->error_reporting = $value;
    if(!isset($this->error_reporting)) $this->error_reporting = self::E_WARNING;
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
    if(!function_exists($this->error_handler))
      $this->error_handler = array('Neevo', 'default_error_handler');
    return $this->error_handler;
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
   * If error_reporting is turned on, throws NeevoException available to catch.

   * @param string $neevo_msg Error message
   * @param bool $warning This error is warning only
   * @return FALSE
   * @access private
   */
  public function error($neevo_msg, $warning = false){
    return $this->driver()->error($neevo_msg, $warning);
  }


  /**
   * Returns some info about connection as an array
   * @return array
   */
  public function info(){
    $info = $this->options;
    unset($info['password']);
    $info['queries'] = $this->queries();
    $info['last'] = $this->last();
    $info['table_prefix'] = $this->prefix();
    $info['error_reporting'] = $this->error_reporting();
    $info['memory_usage'] = $this->memory();
    $info['version'] = $this->version(false);

    return $info;
  }


  /**
   * Neevo's default error handler function
   * @param string $msg Error message
   * @return void
   * @access private
   */
  public static function default_error_handler($msg){
    echo "<b>Neevo error:</b> $msg.\n";
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
