<?php
/**
 * Neevo - Tiny database layer for PHP. (http://neevo.smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * Copyright (c) 2013 Smasty (http://smasty.net)
 *
 */

namespace Neevo;


/**
 * Interface implemented by all Neevo drivers.
 *
 * All Neevo drivers **must** implement this interface, not only reproduce all it's
 * methods, or they won't be recognised as valid drivers.
 *
 * If something is not implemented, the method **must** throw Neevo\Drivers\ImplementationException.
 * The exception will be catched and Neevo will decide, what to do next.
 *
 * If something is not supported by the driver (e.g. number of result rows on unbuffered queries)
 * the driver should throw Neevo\DriverException.
 *
 * When the driver needs to rewrite default output of SQL commands, it **must**
 * extend **Neevo\Parser** class. For proper use, see
 * "source of **Neevo\Parser** class":https://github.com/smasty/Neevo/blob/master/src/Neevo/Parser.php.
 *
 * @author Smasty
 */
interface DriverInterface
{


    /**
     * Checks for required PHP extension.
     * @throws DriverException
     */
    public function __construct();


    /**
     * Creates connection to database.
     * @param array $config Configuration options
     */
    public function connect(array $config);


    /**
     * Closes the connection.
     */
    public function closeConnection();


    /**
     * Frees memory used by given result.
     * @param resource $resultSet
     * @return bool
     */
    public function freeResultSet($resultSet);


    /**
     * Executes given SQL statement.
     * @param string $queryString
     * @return resource|bool
     */
    public function runQuery($queryString);


    /**
     * Begins a transaction if supported.
     * @param string $savepoint
     */
    public function beginTransaction($savepoint = null);


    /**
     * Commits statements in a transaction.
     * @param string $avepoint
     */
    public function commit($savepoint = null);


    /**
     * Rollbacks changes in a transaction.
     * @param string $savepoint
     */
    public function rollback($savepoint = null);


    /**
     * Fetches row from given result set as an associative array.
     * @param resource $resultSet
     * @return array
     */
    public function fetch($resultSet);


    /**
     * Moves internal result pointer.
     * @param resource $resultSet
     * @param int $offset
     * @return bool
     */
    public function seek($resultSet, $offset);


    /**
     * Returns the ID generated in the INSERT statement.
     * @return int
     */
    public function getInsertId();


    /**
     * Randomizes result order.
     * @param BaseStatement $statement
     */
    public function randomizeOrder(BaseStatement $statement);


    /**
     * Returns the number of rows in the given result set.
     * @param resource $resultSet
     * @return int|bool
     */
    public function getNumRows($resultSet);


    /**
     * Returns the number of affected rows in previous operation.
     * @return int
     */
    public function getAffectedRows();


    /**
     * Escapes given value.
     * @param mixed $value
     * @param string $type
     * @return mixed
     */
    public function escape($value, $type);


    /**
     * Decodes given value.
     * @param mixed $value
     * @param string $type
     * @return mixed
     */
    public function unescape($value, $type);


    /**
     * Returns the PRIMARY KEY column for given table.
     * @param string $table
     * @return string|NULL
     */
    public function getPrimaryKey($table);


    /**
     * Returns types of columns in given result set.
     * @param resource $resultSet
     * @param string $table
     * @return array
     */
    public function getColumnTypes($resultSet, $table);
}
