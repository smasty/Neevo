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
 * - **build()**           - Base structure of SQL command. **Must be declared** when some of following method are beeing declared.
 * - **buildColName()**    - Column names, including table.column syntax
 * - **buildSelectCols()** - `[SELECT] "col1, table.col2" ...`
 * - **buildInsertData()** - `[INSERT INTO] "(col1, col2) VALUES (val1, val2)" ...`
 * - **buildUpdateData()** - `[UPDATE table] "SET col1 = val1, col2 = val2 ..."`
 * - **buildWhere()**      - WHERE clause
 * - **buildOrdering()**   - ORDER BY clause
 * - **buildGrouping()**   - GROUP BY clause
 * 
 * For proper use, see "source of **NeevoQueryBuilder** class":./source-neevo.NeevoQueryBuilder.php.html.
 *
 * @package NeevoDrivers
 */
interface INeevoDriver {


  /**
   * If driver extension is loaded, sets Neevo reference, otherwise throw exception
   * @param Neevo
   * @throws NeevoException
   * @return void
   */
  public function  __construct(Neevo $neevo);

  /**
   * Creates connection to database
   * @param array Configuration options
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
   * @param resource
   * @return bool
   */
  public function free($resultSet);


  /**
   * Executes given SQL query
   * @param string Query-string.
   * @return resource|bool
   */
  public function query($query_string);


  /**
   * Error message with driver-specific additions
   * @param string Error message
   * @return array Format: array($error_message, $error_number)
   */
  public function error($neevo_msg);


  /**
   * Fetches row from given Query result set as associative array.
   * @param resource Result set
   * @return array
   */
  public function fetch($resultSet);


  /**
   * Fetches all rows from given result set as associative arrays.
   * @param resource Result set
   * @return array
   */
  public function fetchAll($resultSet);


  /**
   * Move internal result pointer
   * @param resource Query resource
   * @param int Row number of the new result pointer.
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
   * @param NeevoResult NeevoResult instance
   * @return NeevoResult
   */
  public function rand(NeevoResult $query);


  /**
   * Number of rows in result set.
   * @param resource
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
   * @param string
   * @return string|null
   */
  public function getPrimaryKey($table);


  /**
   * Escapes given value
   * @param mixed
   * @param int Type of value (Neevo::TEXT, Neevo::BOOL...)
   * @return mixed
   */
  public function escape($value, $type);
  
}
