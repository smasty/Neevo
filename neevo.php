<?php
/**
 * Neevo - Tiny open-source database abstraction layer for PHP
 *
 * Copyright 2010 Martin Srank (http://smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * @copyright  Copyright (c) 2010 Martin Srank (http://smasty.net)
 * @license    http://www.opensource.org/licenses/mit-license.php  MIT license
 * @link       http://labs.smasty.net/neevo/
 * @package    Neevo
 * @version    0.02dev
 *
 */

include dirname(__FILE__). "/neevo/NeevoQuery.php";
include dirname(__FILE__). "/neevo/NeevoStatic.php";
include dirname(__FILE__). "/neevo/INeevoDriver.php";

include dirname(__FILE__). "/neevo/NeevoDriverMySQL.php";

/**
 * Main Neevo layer class
 * @package Neevo
 */
class Neevo{

  // Fields
  private $resource, $last, $table_prefix, $queries, $error_reporting, $driver;
  private $options = array();

  // Error-reporting levels
  const E_NONE    = 1;
  const E_CATCH   = 2;
  const E_WARNING = 3;
  const E_STRICT  = 4;


  /**
   * Neevo main class.
   * @param array $opts Array of options in following format:
   * <pre>Array(
   *   driver          =>  driver to use
   *   host            =>  localhost,
   *   username        =>  username,
   *   password        =>  password,
   *   database        =>  database_name,
   *   encoding        =>  utf8,
   *   table_prefix    =>  prefix_
   *   error_reporting =>  error_reporting_level; See error_reporting() for possible values.
   * );</pre>
   * @see Neevo::error_reporting(), Neevo::prefix()
   */
  public function __construct(array $opts){
    $this->set_driver($opts['driver']);
    $this->connect($opts);
    if($opts['error_reporting']) $this->error_reporting = $opts['error_reporting'];
    if($opts['table_prefix']) $this->table_prefix = $opts['table_prefix'];
  }


  /**
   * Closes connection to server.
   */
  public function  __destruct(){
    $this->driver()->close($this->resource);
  }


  /**
   * Sets Neevo SQL driver to use
   * @param string $driver Driver name
   * @return void
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

  public function set_resource($resource){
    $this->resource = $resource;
  }

  public function set_options(array $opts){
    $this->options = $opts;
  }

  
  /**
   * Connects to database server, selects database and sets encoding (if defined)
   * @access private
   * @param array $opts
   * @return bool
   */
  private function connect(array $opts){
    return $this->driver()->connect($opts);
  }


  /**
   * Sets and/or returns table prefix
   * @param string $prefix Table prefix to set
   * @return mixed
   */
  public function prefix($prefix = null){
    if(isset($prefix)) $this->table_prefix = $prefix;
    return $this->table_prefix;
  }


  /**
   * Sets and/or returns error-reporting
   * @param int $value Value of error-reporting.
   * Possible values:
   * <ul><li>Neevo::E_NONE: Turns Neevo error-reporting off</li>
   * <li>Neevo::E_CATCH: Catches all Neevo exceptions by default handler</li>
   * <li>Neevo::E_WARNING: Catches only Neevo warnings</li>
   * <li>Neevo::E_STRICT: Catches no Neevo exceptions</li></ul>
   * @return int
   */
  public function error_reporting($value = null){
    if(isset($value)) $this->error_reporting = $value;
    if(!isset($this->error_reporting)) $this->error_reporting = self::E_WARNING;
    return $this->error_reporting;
  }


  /**
   * Sets and/or returns last executed query
   * @param NeevoQuery $last Last executed query
   * @return NeevoQuery
   */
  public function last(NeevoQuery $last = null){
    if($last instanceof NeevoQuery) $this->last = $last;
    return $this->last;
  }


  public function queries($val = null){
    if(is_numeric($val)) $this->queries += $val;
    return $this->queries;
  }


  /**
   * Returns resource identifier
   * @return resource
   */
  public function resource(){
    return $this->resource;
  }


  /**
   * Fetches data
   * @param resource $resource
   * @return mixed
   * @see NeevoQuery::fetch()
   */
  public function fetch($resource){
    return $this->driver()->fetch($resource);
  }


  /**
   * Performs Query
   * @access private
   * @param NeevoQuery $query Query to perform.
   * @param bool $catch_error Catch exception by default if mode is not E_STRICT
   * @return resource
   */
  public final function query(NeevoQuery $query, $catch_error = false){
    $q = $this->driver()->query($query->build(), $this->resource());
    $this->queries(1);
    $this->last($query);
    if($q) return $q;
    else return $this->error('Query failed', $catch_error);
  }


  /**
   * Creates NeevoQuery object for SELECT query
   * @param mixed $columns Array or comma-separated list of columns to select
   * @param string $table Database table to use for selecting
   * @return NeevoQuery
   */
  public final function select($columns, $table){
    $q = new NeevoQuery($this, 'select', $table);
    return $q->cols($columns);
  }


  /**
   * Creates NeevoQuery object for INSERT query
   * @param string $table Database table to use for inserting
   * @param array $data Associative array of values to insert in format column_name=>column_value
   * @return NeevoQuery
   */
  public final function insert($table, array $data){
    $q = new NeevoQuery($this, 'insert', $table);
    return $q->data($data);
  }


  /**
   * Creates NeevoQuery object for UPDATE query
   * @param string $table Database table to use for updating
   * @param array $data Associative array of values for update in format column_name=>column_value
   * @return NeevoQuery
   */
  public final function update($table, array $data){
    $q = new NeevoQuery($this, 'update', $table);
    return $q->data($data);
  }


  /**
   * Creates NeevoQuery object for DELETE query
   * @param string $table Database table to use for deleting
   * @return NeevoQuery
   */
  public final function delete($table){
    return new NeevoQuery($this, 'delete', $table);
  }


  /**
   * If error_reporting is turned on, throws NeevoException available to catch.
   * @param string $neevo_msg Error message
   * @param bool $catch Catch this error or not
   * @return false
   */
  public function error($neevo_msg, $catch = false){
    $this->driver()->error($neevo_msg, $catch);
    return false;
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

    return $info;
  }


  /**
   * Returns script memory usage
   * @return string
   */
  public function memory(){
    return NeevoStatic::filesize(memory_get_usage(true));
  }

}


/**
 * Neevo Exceptions
 * @package Neevo
 */
class NeevoException extends Exception{};

?>
