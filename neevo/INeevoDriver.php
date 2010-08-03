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

/**
 * Neevo Driver interface
 * @package Neevo
 */
interface INeevoDriver {


  /* Character used as column quote, e.g `column` in MySQL
  const COL_QUOTE; */
  /* Character ussed to escape quotes in queries, e.g. \ in MySQL
  const ESCAPE_CHAR; */


  /* @var Neevo $neevo Reference to main Neevo object
  private $neevo; */


  /**
   * If driver extension is loaded, sets Neevo reference, otherwise throw exception
   * @param Neevo $neevo
   * @throws NeevoException
   */
  public function  __construct($neevo);

  /**
   * Connects to database server, selects database and sets encoding (if defined)
   * @param array $opts Array of options in following format:
   * <pre>Array(
   *   host            =>  localhost,
   *   username        =>  username,
   *   password        =>  password,
   *   database        =>  database_name,
   *   encoding        =>  utf8
   * );</pre>
   * @return bool
   */
  public function connect(array $opts);


  /**
   * Closes given resource
   * @param resource $resource
   */
  public function close($resource);


  /**
   * Executes given SQL query
   * @param string $query_string Query-string.
   * @param resource Connection resource
   * @return resource
   */
  public function query($query_string, $resource);


  /**
   * If error_reporting is turned on, throws NeevoException available to catch.
   * @param string $neevo_msg Error message
   * @throws NeevoException
   * @return false
   */
  public function error($neevo_msg, $catch);


  /**
   * Fetches data from given Query resource
   * @param resource $resource Query resource
   * @return mixed Array or string (if only one value is returned) or FALSE (if nothing is returned).
   */
  public function fetch($resource);


  /**
   * Move internal result pointer
   * @param resource $resource Query resource
   * @param int $row_number Row number of the new result pointer.
   * @return bool
   */
  public function seek($resource, $row_number);


  /**
   * Randomize result order.
   * @param NeevoQuery $query NeevoQuery instance
   * @return NeevoQuery
   */
  public function rand(NeevoQuery $query);


  /**
   * Returns number of affected rows for INSERT/UPDATE/DELETE queries and number of rows in result for SELECT queries
   * @param NeevoQuery $query NeevoQuery instance
   * @param bool $string Return rows as a string ("Rows: 5", "Affected: 10"). Default: FALSE
   * @return mixed Number of rows (int) or FALSE
   */
  public function rows(NeevoQuery $query, $string);

  /**
   * Builds Query from NeevoQuery instance
   * @param NeevoQuery $query NeevoQuery instance
   * @return string the Query
   */
  public function build(NeevoQuery $query);
}
?>