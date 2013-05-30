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
use Neevo\BaseStatement;
use Neevo\Connection;
use Neevo\DriverException;
use Neevo\DriverInterface;
use Neevo\Manager;
use Neevo\Parser;
use SQLite3;
use SQLite3Result;

/**
 * Neevo SQLite 3 driver (PHP extension 'sqlite3')
 *
 * Driver configuration:
 * - database (or file)
 * - memory (bool) => use an in-memory database (overrides 'database')
 * - charset => Character encoding to set (defaults to utf-8)
 * - dbcharset => Database character encoding (will be converted to 'charset')
 *
 * - updateLimit (bool) => Set TRUE if SQLite driver was compiled with SQLITE_ENABLE_UPDATE_DELETE_LIMIT
 * - resource (instance of SQLite3) => Existing SQLite 3 link
 * - lazy, table_prefix => see {@see Neevo\Connection}
 *
 * Since SQLite 3 only allows unbuffered queries, number of result rows and seeking
 * is not supported for this driver.
 *
 * @author Smasty
 */
class SQLite3Driver extends Parser implements DriverInterface
{


    /** @var string */
    private $dbCharset;

    /** @var string */
    private $charset;

    /** @var bool */
    private $updateLimit;

    /** @var SQLite3Result */
    private $resource;

    /** @var int */
    private $affectedRows;

    /** @var array */
    private $tblData = array();


    /**
     * Checks for required PHP extension.
     * @throws DriverException
     */
    public function __construct(BaseStatement $statement = null)
    {
        if (!extension_loaded("sqlite3")) {
            throw new DriverException("Cannot instantiate Neevo SQLite 3 driver - PHP extension 'sqlite3' not loaded.");
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
        Connection::alias($config, 'database', 'file');
        Connection::alias($config, 'updateLimit', 'update_limit');

        $defaults = array(
            'memory' => false,
            'resource' => null,
            'updateLimit' => false,
            'charset' => 'UTF-8',
            'dbcharset' => 'UTF-8'
        );

        $config += $defaults;

        if ($config['memory']) {
            $config['database'] = ':memory:';
        }

        // Connect
        if ($config['resource'] instanceof SQLite3) {
            $connection = $config['resource'];
        } elseif (!isset($config['database'])) {
            throw new DriverException("No database file selected.");
        } else {
            try {
                $connection = new SQLite3($config['database']);
            } catch (Exception $e) {
                throw new DriverException($e->getMessage(), $e->getCode());
            }
        }

        if (!$connection instanceof SQLite3) {
                throw new DriverException("Opening database file '$config[database]' failed.");
        }

        $this->resource = $connection;
        $this->updateLimit = (bool) $config['updateLimit'];

        // Set charset
        $this->dbCharset = $config['dbcharset'];
        $this->charset = $config['charset'];
        if (strcasecmp($this->dbCharset, $this->charset) === 0) {
            $this->dbCharset = $this->charset = null;
        }
    }


    /**
     * Closes the connection.
     */
    public function closeConnection()
    {
        if ($this->resource instanceof SQLite3) {
            $this->resource->close();
        }
    }


    /**
     * Frees memory used by given result.
     *
     * Neevo\Result automatically NULLs the resource, so this is not necessary.
     * @param SQLite3Result $resultSet
     * @return bool
     */
    public function freeResultSet($resultSet)
    {
        return true;
    }


    /**
     * Executes given SQL statement.
     * @param string $queryString
     * @return SQLite3Result|bool
     * @throws DriverException
     */
    public function runQuery($queryString)
    {

        $this->affectedRows = false;
        if ($this->dbCharset !== null) {
            $queryString = iconv($this->charset, $this->dbCharset . '//IGNORE', $queryString);
        }

        $result = $this->resource->query($queryString);

        if ($result === false) {
            throw new DriverException($this->resource->lastErrorMsg(), $this->resource->lastErrorCode(), $queryString);
        }

        $this->affectedRows = $this->resource->changes();
        return $result;
    }


    /**
     * Begins a transaction if supported.
     * @param string $savepoint
     */
    public function beginTransaction($savepoint = null)
    {
        $this->runQuery($savepoint ? "SAVEPOINT $savepoint" : 'BEGIN');
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
     * @param SQLite3Result $resultSet
     * @return array
     */
    public function fetch($resultSet)
    {
        $row = $resultSet->fetchArray(SQLITE3_ASSOC);
        $charset = $this->charset === null ? null : $this->charset . '//TRANSLIT';

        if ($row) {
            $fields = array();
            foreach ($row as $key => $val) {
                if ($charset !== null && is_string($val)) {
                    $val = iconv($this->dbcharset, $charset, $val);
                }
                $fields[str_replace(array('[', ']'), '', $key)] = $val;
            }
            return $fields;
        }
        return $row;
    }


    /**
     * Moves internal result pointer.
     *
     * Not supported because of unbuffered queries.
     * @param SQLite3Result $resultSet
     * @param int $offset
     * @return bool
     * @throws DriverException
     */
    public function seek($resultSet, $offset)
    {
        throw new DriverException('Cannot seek on unbuffered result.');
    }


    /**
     * Returns the ID generated in the INSERT statement.
     * @return int
     */
    public function getInsertId()
    {
        return $this->resource->lastInsertRowID();
    }


    /**
     * Randomizes result order.
     * @param BaseStatement $tatement
     */
    public function randomizeOrder(BaseStatement $statement)
    {
        $statement->order('RANDOM()');
    }


    /**
     * Returns the number of rows in the given result set.
     *
     * Not supported because of unbuffered queries.
     * @param SQLite3Result $resultSet
     * @return int|bool
     * @throws DriverException
     */
    public function getNumRows($resultSet)
    {
        throw new DriverException('Cannot count rows on unbuffered result.');
    }


    /**
     * Returns the umber of affected rows in previous operation.
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
     * @throws InvalidArgumentException
     * @return mixed
     */
    public function escape($value, $type)
    {
        switch ($type) {
            case Manager::BOOL:
                return $value ? 1 : 0;
            case Manager::TEXT:
                return "'" . $this->resource->escapeString($value) . "'";
            case Manager::IDENTIFIER:
                return str_replace('[*]', '*', '[' . str_replace('.', '].[', $value) . ']');
            case Manager::BINARY:
                return "X'" . bin2hex((string) $value) . "'";
            case Manager::DATETIME:
                return ($value instanceof DateTime) ? $value->format("'Y-m-d H:i:s'") : date("'Y-m-d H:i:s'", $value);
            default:
                throw new InvalidArgumentException('Unsupported data type');
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
        $pos = strpos($table, '.');
        if ($pos !== false) {
            $table = substr($table, $pos + 1);
        }
        if (isset($this->tblData[$table])) {
            $sql = $this->tblData[$table];
        } else {
            $q = $this->runQuery("SELECT sql FROM sqlite_master WHERE tbl_name='$table'");
            $r = $this->fetch($q);
            if ($r === false) {
                return '';
            }
            $this->tblData[$table] = $sql = $r['sql'];
        }

        $sql = explode("\n", $sql);
        foreach ($sql as $field) {
            $field = trim($field);
            if (stripos($field, 'PRIMARY KEY') !== false && $key === '') {
                $key = preg_replace('~^"(\w+)".*$~i', '$1', $field);
            }
        }
        return $key;
    }


    /**
     * Returns types of columns in given result set.
     * @param SQLite3Result $resultSet
     * @param string $table
     * @return array
     */
    public function getColumnTypes($resultSet, $table)
    {
        if ($table === null) {
            return array();
        }
        if (isset($this->tblData[$table])) {
            $sql = $this->tblData[$table];
        } else {
            $q = $this->runQuery("SELECT sql FROM sqlite_master WHERE tbl_name='$table'");
            $r = $this->fetch($q);
            if ($r === false) {
                return array();
            }
            $this->tblData[$table] = $sql = $r['sql'];
        }
        $sql = explode("\n", $sql);

        $cols = array();
        foreach ($sql as $field) {
            $field = trim($field);
            preg_match('~^"(\w+)"\s+(integer|real|numeric|text|blob).+$~i', $field, $m);
            if (isset($m[1], $m[2])) {
                $cols[$m[1]] = $m[2];
            }
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
        return $this->updateLimit ? $this->applyLimit($sql . $this->clauses[3]) : $sql;
    }


    /**
     * Parses DELETE statement.
     * @return string
     */
    protected function parseDeleteStmt()
    {
        $sql = parent::parseDeleteStmt();
        return $this->updateLimit ? $this->applyLimit($sql . $this->clauses[3]) : $sql;
    }
}
