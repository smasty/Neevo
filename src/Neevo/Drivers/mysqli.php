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

namespace Neevo\Drivers;

use DateTime;
use InvalidArgumentException;
use mysqli;
use mysqli_result;
use Neevo\BaseStatement;
use Neevo\DriverException;
use Neevo\DriverInterface;
use Neevo\Manager;
use Neevo\Parser;

/**
 * Neevo MySQLi driver (PHP extension 'mysqli')
 *
 * Driver configuration:
 * - host => MySQL server name or address
 * - port (int) => MySQL server port
 * - socket
 * - username
 * - password
 * - database => database to select
 * - charset => Character encoding to set (defaults to utf8)
 * - peristent (bool) => Try to find a persistent link
 * - unbuffered (bool) => Sends query without fetching and buffering the result
 *
 * - resource (instance of mysqli) => Existing MySQLi link
 * - lazy, table_prefix... => see {@see Neevo\Connection}
 *
 * @author Smasty
 */
class MySQLiDriver extends Parser implements DriverInterface
{


    /** @var mysqli_result */
    private $resource;

    /** @var bool */
    private $unbuffered;

    /** @var int */
    private $affectedRows;


    /**
     * Checks for required PHP extension.
     * @throws DriverException
     */
    public function __construct(BaseStatement $statement = null)
    {
        if (!extension_loaded("mysqli")) {
            throw new DriverException("Cannot instantiate Neevo MySQLi driver - PHP extension 'mysqli' not loaded.");
        }
        if ($statement instanceof BaseStatement) {
            parent::__construct($statement);
        }
    }


    /**
     * Creates connection to database.
     * @param array $config Configuration options
     * @throws DriverException
     */
    public function connect(array $config)
    {

        // Defaults
        $defaults = array(
            'resource' => null,
            'charset' => 'utf8',
            'username' => ini_get('mysqli.default_user'),
            'password' => ini_get('mysqli.default_pw'),
            'database' => '',
            'socket' => ini_get('mysqli.default_socket'),
            'port' => ini_get('mysqli.default_port'),
            'host' => ini_get('mysqli.default_host'),
            'persistent' => false,
            'unbuffered' => false
        );

        $config += $defaults;

        // Connect
        if ($config['resource'] instanceof mysqli) {
            $this->resource = $config['resource'];
        } else {
            $this->resource = new mysqli(
                $config['host'],
                $config['username'],
                $config['password'],
                $config['database'],
                $config['port'],
                $config['socket']
            );
        }

        if ($this->resource->connect_errno) {
            throw new DriverException($this->resource->connect_error, $this->resource->connect_errno);
        }

        // Set charset
        if ($this->resource instanceof mysqli) {
            $ok = @$this->resource->set_charset($config['charset']);
            if (!$ok) {
                $this->runQuery("SET NAMES " . $config['charset']);
            }
        }

        $this->unbuffered = $config['unbuffered'];
    }


    /**
     * Closes the connection.
     */
    public function closeConnection()
    {
        @$this->resource->close();
    }


    /**
     * Frees memory used by given result set.
     * @param mysqli_result $resultSet
     * @return bool
     */
    public function freeResultSet($resultSet)
    {
        if ($resultSet instanceof mysqli_result) {
            $resultSet->free();
        }
    }


    /**
     * Executes given SQL statement.
     * @param string $queryString
     * @return mysqli_result|bool
     * @throws DriverException
     */
    public function runQuery($queryString)
    {

        $this->affectedRows = false;
        $result = $this->resource->query($queryString, $this->unbuffered ? MYSQLI_USE_RESULT : MYSQLI_STORE_RESULT);

        $error = preg_replace(
            '~You have an error in your SQL syntax; check the manual that corresponds' +
            ' to your \w+ server version for the right syntax to use~i',
            'Syntax error',
            $this->resource->error
        );
        if ($error && $result === false) {
            throw new DriverException($error, $this->resource->errno, $queryString);
        }

        $this->affectedRows = $this->resource->affected_rows;
        return $result;
    }


    /**
     * Begins a transaction if supported.
     * @param string $savepoint
     */
    public function beginTransaction($savepoint = null)
    {
        $this->runQuery($savepoint ? "SAVEPOINT $savepoint" : 'START TRANSACTION');
    }


    /**
     * Commits statements in a transaction.
     * @param string $savepoint
     */
    public function commit($savepoint = null)
    {
        $this->runQuery($savepoint ? "RELEASE SAVEPOINT $savepoint" : 'COMMIT');
    }


    /**
     * Rollbacks changes in a transaction.
     * @param string $savepoint
     */
    public function rollback($savepoint = null)
    {
        $this->runQuery($savepoint ? "ROLLBACK TO SAVEPOINT $savepoint" : 'ROLLBACK');
    }


    /**
     * Fetches row from given result set as an associative array.
     * @param mysqli_result $resultSet
     * @return array
     */
    public function fetch($resultSet)
    {
        return $resultSet->fetch_assoc();
    }


    /**
     * Moves internal result pointer.
     * @param mysqli_result $resultSet
     * @param int
     * @return bool
     * @throws DriverException
     */
    public function seek($resultSet, $offset)
    {
        if ($this->unbuffered) {
            throw new DriverException('Cannot seek on unbuffered result.');
        }
        return $resultSet->data_seek($offset);
    }


    /**
     * Returns the ID generated in the INSERT statement.
     * @return int
     */
    public function getInsertId()
    {
        return $this->resource->insert_id;
    }


    /**
     * Randomizes result order.
     * @param BaseStatement $statement
     */
    public function randomizeOrder(BaseStatement $statement)
    {
        $statement->order('RAND()');
    }


    /**
     * Returns the number of rows in the given result set.
     * @param mysqli_result $resultSet
     * @return int|bool
     * @throws DriverException
     */
    public function getNumRows($resultSet)
    {
        if ($this->unbuffered) {
            throw new DriverException('Cannot count rows on unbuffered result.');
        }
        if ($resultSet instanceof mysqli_result) {
            return $resultSet->num_rows;
        }
        return false;
    }


    /**
     * Returns the number of affected rows in previous operation.
     * @return int
     */
    public function getAffectedRows()
    {
        return $this->affectedRows;
    }


    /**
     * Escapes given value.
     * @param mixed $value
     * @param string $type
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function escape($value, $type)
    {
        switch ($type) {
            case Manager::BOOL:
                return $value ? 1 : 0;
            case Manager::TEXT:
                return "'" . $this->resource->real_escape_string($value) . "'";
            case Manager::IDENTIFIER:
                return str_replace('`*`', '*', '`' . str_replace('.', '`.`', str_replace('`', '``', $value)) . '`');
            case Manager::BINARY:
                return "_binary'" . mysqli_real_escape_string($this->resource, $value) . "'";
            case Manager::DATETIME:
                return ($value instanceof DateTime) ? $value->format("'Y-m-d H:i:s'") : date("'Y-m-d H:i:s'", $value);
            default:
                throw new InvalidArgumentException('Unsupported data type.');
                break;
        }
    }


    /**
     * Decodes given value.
     * @param mixed $value
     * @param string $type
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function unescape($value, $type)
    {
        if ($type === Manager::BINARY) {
            return $value;
        }
        throw new InvalidArgumentException('Unsupported data type.');
    }


    /**
     * Returns the PRIMARY KEY column for given table.
     * @param string $table
     * @return string
     */
    public function getPrimaryKey($table)
    {
        $key = '';
        $q = $this->runQuery('SHOW FULL COLUMNS FROM ' . $table);
        while ($col = $this->fetch($q)) {
            if (strtolower($col['Key']) === 'pri' && $key === '') {
                $key = $col['Field'];
            }
        }
        return $key;
    }


    /**
     * Returns types of columns in given result set.
     * @param mysqli_result $resultset
     * @param string $table
     * @return array
     */
    public function getColumnTypes($resultSet, $table)
    {
        static $colTypes;
        if (empty($colTypes)) {
            $constants = get_defined_constants(true);
            foreach ($constants['mysqli'] as $type => $code) {
                if (strncmp($type, 'MYSQLI_TYPE_', 12) === 0) {
                    $colTypes[$code] = strtolower(substr($type, 12));
                }
            }
            $colTypes[MYSQLI_TYPE_LONG] = $colTypes[MYSQLI_TYPE_SHORT] = $colTypes[MYSQLI_TYPE_TINY] = 'int';
        }

        $cols = array();
        while ($field = $resultSet->fetch_field()) {
            $cols[$field->name] = $colTypes[$field->type];
        }
        return $cols;
    }


    /**
     * Parses UPDATE statement.
     * @return string
     */
    protected function parseUpdateStmt()
    {
        $sql = parent::parseUpdateStmt();
        return $this->applyLimit($sql . $this->clauses[3]);
    }


    /**
     * Parses DELETE statement.
     * @return string
     */
    protected function parseDeleteStmt()
    {
        $sql = parent::parseDeleteStmt();
        return $this->applyLimit($sql . $this->clauses[3]);
    }
}
