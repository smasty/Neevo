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
 * @link     http://neevo.smasty.net
 * @package  Neevo
 *
 */

/**
 * Neevo Driver interface
 * @package NeevoDrivers
 */
interface INeevoDriver {


  /**
   * If driver extension is loaded, sets Neevo reference, otherwise throw exception
   * @param Neevo $neevo
   * @throws NeevoException
   * @return void
   */
  public function  __construct(Neevo $neevo);

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
   * @return void
   */
  public function connect(array $opts);


  /**
   * Closes given resource
   * @return void
   */
  public function close();


  /**
   * Frees memory used by result
   * @param resource $resultSet
   * @return bool
   */
  public function free($resultSet);


  /**
   * Executes given SQL query
   * @param string $query_string Query-string.
   * @return resource
   */
  public function query($query_string);


  /**
   * Error message with driver-specific additions
   * @param string $neevo_msg Error message
   * @return string
   */
  public function error($neevo_msg);


  /**
   * Fetches row from given Query resource as associative array.
   * @param resource $resultSet Query resource
   * @return array
   */
  public function fetch($resultSet);


  /**
   * Move internal result pointer
   * @param resource $resultSet Query resource
   * @param int $row_number Row number of the new result pointer.
   * @return bool
   */
  public function seek($resultSet, $row_number);


  /**
   * Get the ID generated in the INSERT query
   * @return int
   */
  public function insertId();


  /**
   * Randomize result order.
   * @param NeevoQuery $query NeevoQuery instance
   * @return NeevoQuery
   */
  public function rand(NeevoQuery $query);


  /**
   * Number of rows in result set.
   * @param resource $resultSet
   * @return int|FALSE
   */
  public function rows($resultSet);


  /**
   * Number of affected rows in previous operation.
   * @return int
   */
  public function affectedRows();


  /**
   * Builds Query from NeevoQuery instance
   * @param NeevoQuery $query NeevoQuery instance
   * @return string the Query
   */
  public function build(NeevoQuery $query);


  /**
   * Escapes given value
   * @param mixed $value
   * @param int $type Type of value (Neevo::TEXT, Neevo::BOOL...)
   * @return mixed
   */
  public function escape($value, $type);


  /**
   * Return Neevo class instance
   * @return Neevo
   */
  public function neevo();
}
