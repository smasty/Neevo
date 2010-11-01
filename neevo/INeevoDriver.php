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

/**
 * Interface implemented by all Neevo drivers.
 *
 * All Neevo drivers **must** implement this interface, not only reproduce all it's
 * methods, or they won't be recognised as valid driver classes.
 *
 * If something is not supported in the driver, the method **must** throw NotImplementedException.
 * The exception will be catched and Neevo will decide, what to do next.
 *
 * When the driver needs to rewrite default output for SQL commands, it **must**
 * extend **NeevoQueryBuilder** class.
 * Then following methods can be declared to rewrite SQL command output:
 * - **build()** - Base structure of SQL command. **Must be declared** when some of following method are beeing declared.
 * - **buildColName()** - Column names, including table.column syntax
 * - **buildSelectCols()** - `[SELECT] "col1, table.col2" ...`
 * - **buildInsertData()** - `[INSERT INTO] "(col1, col2) VALUES (val1, val2)" ...`
 * - **buildUpdateData()** - `[UPDATE table] "SET col1 = val1, col2 = val2 ..."`
 * - **buildWhere()** - WHERE clause
 * - **buildOrder()** - ORDER BY clause
 * 
 * For proper use, see "source of **NeevoQueryBuilder** class":./source-neevo.NeevoQueryBuilder.php.html.
 *
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
   * Creates connection to database
   * @param array $config Configuration options
   * @return void
   */
  public function connect(array $config);


  /**
   * Closes connection
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
   * @return resource|bool
   */
  public function query($query_string);


  /**
   * Error message with driver-specific additions
   * @param string $neevo_msg Error message
   * @return array Format: array($error_message, $error_number)
   */
  public function error($neevo_msg);


  /**
   * Fetches row from given Query result set as associative array.
   * @param resource $resultSet Result set
   * @return array
   */
  public function fetch($resultSet);


  /**
   * Fetches all rows from given result set as associative arrays.
   * @param resource $resultSet Result set
   * @return array
   */
  public function fetchAll($resultSet);


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
   * @param NeevoResult $query NeevoResult instance
   * @return NeevoResult
   */
  public function rand(NeevoResult $query);


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
   * Name of PRIMARY KEY column for table
   * @param string $table
   * @return string|null
   */
  public function getPrimaryKey($table);


  /**
   * Escapes given value
   * @param mixed $value
   * @param int $type Type of value (Neevo::TEXT, Neevo::BOOL...)
   * @return mixed
   */
  public function escape($value, $type);
  
}
