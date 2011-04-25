<?php
/**
 * Neevo - Tiny database layer for PHP. (http://neevo.smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * Copyright (c) 2011 Martin Srank (http://smasty.net)
 *
 */


/**
 * Interface implemented by all Neevo drivers.
 *
 * All Neevo drivers **must** implement this interface, not only reproduce all it's
 * methods, or they won't be recognised as valid drivers.
 *
 * If something is not implemented, the method **must** throw NeevoImplementationException.
 * The exception will be catched and Neevo will decide, what to do next.
 *
 * If something is not supported by the driver (e.g. number of result rows on unbuffered queries)
 * the driver should throw NeevoDriverException.
 *
 * When the driver needs to rewrite default output of SQL commands, it **must**
 * extend **NeevoStmtParser** class. For proper use, see
 * "source of **NeevoStmtParser** class":./source-neevo.NeevoStmtParser.php.html.
 *
 * @author Martin Srank
 * @package Neevo\Drivers
 */
interface INeevoDriver {


	/**
	 * Check for required PHP extension.
	 * @return void
	 * @throws NeevoDriverException
	 */
	public function __construct();


	/**
	 * Create connection to database.
	 * @param array $config Configuration options
	 * @return void
	 */
	public function connect(array $config);


	/**
	 * Close the connection.
	 * @return void
	 */
	public function close();


	/**
	 * Free memory used by given result.
	 * @param resource $resultSet
	 * @return bool
	 */
	public function free($resultSet);


	/**
	 * Execute given SQL statement.
	 * @param string $queryString
	 * @return resource|bool
	 */
	public function query($queryString);


	/**
	 * Begin a transaction if supported.
	 * @param string $savepoint
	 * @return void
	 */
	public function begin($savepoint = null);


	/**
	 * Commit statements in a transaction.
	 * @param string $avepoint
	 * @return void
	 */
	public function commit($savepoint = null);


	/**
	 * Rollback changes in a transaction.
	 * @param string $savepoint
	 * @return void
	 */
	public function rollback($savepoint = null);


	/**
	 * Fetch row from given result set as an associative array.
	 * @param resource $resultSet
	 * @return array
	 */
	public function fetch($resultSet);


	/**
	 * Move internal result pointer.
	 * @param resource $resultSet
	 * @param int $offset
	 * @return bool
	 */
	public function seek($resultSet, $offset);


	/**
	 * Get the ID generated in the INSERT statement.
	 * @return int
	 */
	public function insertId();


	/**
	 * Randomize result order.
	 * @param NeevoStmtBase $statement
	 * @return void
	 */
	public function rand(NeevoStmtBase $statement);


	/**
	 * Get the number of rows in the given result set.
	 * @param resource $resultSet
	 * @return int|FALSE
	 */
	public function rows($resultSet);


	/**
	 * Get the number of affected rows in previous operation.
	 * @return int
	 */
	public function affectedRows();


	/**
	 * Escape given value.
	 * @param mixed $value
	 * @param string $type
	 * @return mixed
	 */
	public function escape($value, $type);


	/**
	 * Decode given value.
	 * @param mixed $value
	 * @param string $type
	 * @return mixed
	 */
	public function unescape($value, $type);


	/**
	 * Get the PRIMARY KEY column for given table.
	 * @param string $table
	 * @return string|NULL
	 */
	public function getPrimaryKey($table);


	/**
	 * Get types of columns in given result set.
	 * @param resource $resultSet
	 * @param string $table
	 * @return array
	 */
	public function getColumnTypes($resultSet, $table);


}
