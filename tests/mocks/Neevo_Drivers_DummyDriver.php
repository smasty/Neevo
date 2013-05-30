<?php
/**
 * Neevo - Tiny database layer for PHP. (http://neevo.smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * Copyright (c) 2012 Smasty (http://smasty.net)
 *
 */

namespace Neevo\Drivers;

use Neevo\Test\Mocks\DummyResult;
use Neevo\BaseStatement;
use Neevo\DriverException;
use Neevo\DriverInterface;
use Neevo\ImplementationException;
use Neevo\Manager;

/**
 * Dummy Neevo driver.
 */
class DummyDriver implements DriverInterface
{


    private $unbuffered = false;

    private $connected = false;

    private $closed;

    private $transactions = array();

    private $performed = array();

    private $error = false;


    const TRANSACTION_OPEN = 1;

    const TRANSACTION_COMMIT = 2;

    const TRANSACTION_ROLLBACK = 4;


    public function __construct()
    {
    }


    public function connect(array $config)
    {
        $this->unbuffered = isset($config['unbuffered']) ? $config['unbuffered'] : false;
        $this->connected = true;
    }


    public function closeConnection()
    {
        $this->connected = false;
        return $this->closed = true;
    }


    public function freeResultSet($resultSet)
    {
        $resultSet = null;
        return true;
    }


    public function runQuery($queryString)
    {
        if ($this->error == 'query') {
            throw new DriverException;
        }
        if ($queryString) {
            $this->performed[] = $queryString;
            return new DummyResult($queryString, $this);
        }
        return false;
    }


    public function beginTransaction($savepoint = null)
    {
        $this->transactions[$savepoint] = self::TRANSACTION_OPEN;
    }


    public function commit($savepoint = null)
    {
        if (isset($this->transactions[$savepoint])) {
            $this->transactions[$savepoint] = self::TRANSACTION_COMMIT;
        } elseif ($savepoint === null) {
            $this->transactions[count($this->transactions) - 1] = self::TRANSACTION_COMMIT;
        } else {
            throw new DriverException("Invalid savepoint '$savepoint'.");
        }
    }


    public function rollback($savepoint = null)
    {
        if (isset($this->transactions[$savepoint])) {
            $this->transactions[$savepoint] = self::TRANSACTION_ROLLBACK;
        } elseif ($savepoint === null) {
            $this->transactions[count($this->transactions) - 1] = self::TRANSACTION_ROLLBACK;
        } else {
            throw new DriverException("Invalid savepoint '$savepoint'.");
        }
    }


    public function fetch($resultSet)
    {
        return $resultSet->fetch();
    }


    public function seek($resultSet, $offset)
    {
        if ($this->unbuffered) {
            throw new DriverException('Cannot seek on unbuffered result.');
        }
        return $resultSet->seek($offset);
    }


    public function getInsertId()
    {
        if ($this->error == 'insert-id') {
            throw new ImplementationException;
        }
        return 4;
    }


    public function randomizeOrder(BaseStatement $statement)
    {
        $statement->order('RANDOM()');
    }


    public function getNumRows($resultSet)
    {
        if ($this->unbuffered) {
            throw new DriverException('Cannot count rows on unbuffered result.');
        }
        return $resultSet ? 3 : false;
    }


    public function getAffectedRows()
    {
        if ($this->error == 'affected-rows') {
            throw new DriverException;
        }
        return 1;
    }


    public function escape($value, $type)
    {
        return $value;
    }


    public function unescape($value, $type)
    {
        if ($type === Manager::BINARY) {
            return "bin:$value";
        }
    }


    public function getPrimaryKey($table)
    {
        if ($this->error == 'primary-key') {
            throw new DriverException;
        }
        return 'id';
    }


    public function getColumnTypes($resultSet, $table)
    {
        if ($this->error == 'column-types') {
            throw new DriverException;
        }
        return array(
            'id' => 'int',
            'name' => 'text',
            'mail' => 'text'
        );
    }


    public function getRow($i = null)
    {
        if ($i === null) {
            return DummyResult::$data;
        }
        if (isset(DummyResult::$data[$i])) {
            return DummyResult::$data[$i];
        }
        return false;
    }


    // =========== State methods


    public function isClosed()
    {
        return (bool) $this->closed;
    }


    public function isConnected()
    {
        return (bool) $this->connected;
    }


    public function transactionState()
    {
        return end($this->transactions);
    }


    public function performed()
    {
        return $this->performed;
    }


    public function setError($error)
    {
        $this->error = $error;
    }
}
