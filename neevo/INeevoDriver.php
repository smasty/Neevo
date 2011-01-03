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
 * methods, or they won't be recognised as valid drivers.
 *
 * If something is not implemented, the method **must** throw NotImplementedException.
 * The exception will be catched and Neevo will decide, what to do next.
 *
 * If something is not supported by the driver (e.g. number of result rows on unbuffered queries)
 * it's a good thing to throw NotSupportedException.
 *
 * When the driver needs to rewrite default output for SQL commands, it **must**
 * extend **NeevoStmtBuilder** class.
 * Then following methods can than be used to rewrite SQL command output:
 * - **build()**           - Base structure of SQL command.
 * - **buildColName()**    - Column names, including table.column syntax
 * - **buildSelectCols()** - `[SELECT] "col1, table.col2" ...`
 * - **buildInsertData()** - `[INSERT INTO] "(col1, col2) VALUES (val1, val2)" ...`
 * - **buildUpdateData()** - `[UPDATE table] "SET col1 = val1, col2 = val2 ..."`
 * - **buildJoin()**       - JOIN syntax
 * - **buildWhere()**      - WHERE clause
 * - **buildOrdering()**   - ORDER BY clause
 * - **buildGrouping()**   - GROUP BY clause
 * 
 * For proper use, see "source of **NeevoStmtBuilder** class":./source-neevo.NeevoStmtBuilder.php.html.
 *
 * @package NeevoDrivers
 */
interface INeevoDriver {


  /**
   * Check for required PHP extension
   * @param Neevo $neev
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
   * Executes given SQL statement
   * @param string $queryString Query-string.
   * @return resource|bool
   */
  public function query($queryString);


  /**
   * Fetches row from given result set as associative array.
   * @param resource $resultSet Result set
   * @return array
   */
  public function fetch($resultSet);


  /**
   * Move internal result pointer
   * @param resource $resultSet
   * @param int $offset
   * @return bool
   */
  public function seek($resultSet, $offset);


  /**
   * Get the ID generated in the INSERT statement
   * @return int
   */
  public function insertId();


  /**
   * Randomize result order.
   * @param NeevoStmtBase $statement
   */
  public function rand(NeevoStmtBase $statement);


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
   * Escapes given value
   * @param mixed $value
   * @param int $type Type of value (Neevo::TEXT, Neevo::BOOL...)
   * @return mixed
   */
  public function escape($value, $type);


  /**
   * Get PRIMARY KEY column for table
   * @param $table string
   * @return string|null
   */
  public function getPrimaryKey($table);
  
}
