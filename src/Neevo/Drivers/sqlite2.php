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

use DateTime;
use InvalidArgumentException;
use Neevo\BaseStatement;
use Neevo\Connection;
use Neevo\DriverException;
use Neevo\DriverInterface;
use Neevo\Manager;
use Neevo\Parser;


/**
 * Neevo SQLite 2 driver (PHP extension 'sqlite')
 *
 * Driver configuration:
 * - database (or file)
 * - memory (bool) => use an in-memory database (overrides 'database')
 * - charset => Character encoding to set (defaults to utf-8)
 * - dbcharset => Database character encoding (will be converted to 'charset')
 * - persistent (bool) => Try to find a persistent link
 * - unbuffered (bool) => Sends query without fetching and buffering the result
 *
 * - updateLimit (bool) => Set TRUE if SQLite driver was compiled with SQLITE_ENABLE_UPDATE_DELETE_LIMIT
 * - resource (type resource) => Existing SQLite 2 link
 * - lazy, table_prefix... => see {@see Neevo\Connection}
 *
 * @author Smasty
 */
class SQLite2Driver extends Parser implements DriverInterface {


	/** @var string */
	private $charset;

	/** @var string */
	private $dbCharset;

	/** @var bool */
	private $updateLimit;

	/** @var resource */
	private $resource;

	/** @var bool */
	private $unbuffered;

	/** @var bool */
	private $persistent;

	/** @var int */
	private $affectedRows;

	/** @var array */
	private $tblData = array();


	/**
	 * Checks for required PHP extension.
	 * @throws DriverException
	 */
	public function __construct(BaseStatement $statement = null){
		if(!extension_loaded("sqlite"))
			throw new DriverException("Cannot instantiate Neevo SQLite driver - PHP extension 'sqlite' not loaded.");
		if($statement instanceof BaseStatement)
			parent::__construct($statement);
	}


	/**
	 * Creates connection to database.
	 * @param array $config Configuration options
	 * @throws DriverException
	 */
	public function connect(array $config){
		Connection::alias($config, 'database', 'file');
		Connection::alias($config, 'updateLimit', 'update_limit');

		$defaults = array(
			'memory' => false,
			'resource' => null,
			'updateLimit' => false,
			'charset' => 'UTF-8',
			'dbcharset' => 'UTF-8',
			'persistent' => false,
			'unbuffered' => false
		);

		$config += $defaults;

		if($config['memory'])
			$config['database'] = ':memory:';

		// Connect
		if(is_resource($config['resource']))
			$connection = $config['resource'];
		elseif($config['persistent'])
			$connection = @sqlite_popen($config['database'], 0666, $error);
		else
			$connection = @sqlite_open($config['database'], 0666, $error);

		if(!is_resource($connection))
			throw new DriverException("Opening database file '$config[database]' failed.");

		$this->resource = $connection;
		$this->updateLimit = (bool) $config['updateLimit'];

		// Set charset
		$this->dbCharset = $config['dbcharset'];
		$this->charset = $config['charset'];
		if(strcasecmp($this->dbCharset, $this->charset) === 0)
			$this->dbCharset = $this->charset = null;

		$this->unbuffered = $config['unbuffered'];
		$this->persistent = $config['persistent'];
	}


	/**
	 * Closes the connection.
	 */
	public function closeConnection(){
		if(!$this->persistent && $this->resource !== null)
			@sqlite_close($this->resource);
	}


	/**
	 * Free memory used by given result set.
	 * @param resource $resultSet
	 * @return bool
	 */
	public function freeResultSet($resultSet){
		return true;
	}


	/**
	 * Executes given SQL statement.
	 * @param string $queryString
	 * @return resource|bool
	 * @throws DriverException
	 */
	public function runQuery($queryString){

		$this->affectedRows = false;
		if($this->dbCharset !== null)
			$queryString = iconv($this->charset, $this->dbCharset . '//IGNORE', $queryString);

		if($this->unbuffered)
			$result = @sqlite_unbuffered_query($this->resource, $queryString, null, $error);
		else
			$result = @sqlite_query($this->resource, $queryString, null, $error);

		if($error && $result === false)
			throw new DriverException($error, sqlite_last_error($this->resource), $queryString);

		$this->affectedRows = @sqlite_changes($this->resource);
		return $result;
	}


	/**
	 * Begins a transaction if supported.
	 * @param string $savepoint
	 */
	public function beginTransaction($savepoint = null){
		$this->runQuery('BEGIN');
	}


	/**
	 * Commits statements in a transaction.
	 * @param string $savepoint
	 */
	public function commit($savepoint = null){
		$this->runQuery('COMMIT');
	}


	/**
	 * Rollbacks changes in a transaction.
	 * @param string $savepoint
	 */
	public function rollback($savepoint = null){
		$this->runQuery('ROLLBACK');
	}


	/**
	 * Fetches row from given result set as an associative array.
	 * @param resource $resultSet
	 * @return array
	 */
	public function fetch($resultSet){
		$row = @sqlite_fetch_array($resultSet, SQLITE_ASSOC);
		if($row){
			$charset = $this->charset === null ? null : $this->charset . '//TRANSLIT';

			$fields = array();
			foreach($row as $key => $val){
				if($charset !== null && is_string($val))
					$val = iconv($this->dbcharset, $charset, $val);
				$key = str_replace(array('[', ']'), '', $key);
				$pos = strpos($key, '.');
				if($pos !== false)
					$key = substr($key, $pos + 1);
				$fields[$key] = $val;
			}
			$row = $fields;
		}
		return $row;
	}


	/**
	 * Moves internal result pointer.
	 * @param resource $resultSet
	 * @param int $offset
	 * @return bool
	 * @throws DriverException
	 */
	public function seek($resultSet, $offset){
		if($this->unbuffered)
			throw new DriverException('Cannot seek on unbuffered result.');
		return @sqlite_seek($resultSet, $offset);
	}


	/**
	 * Returns the ID generated in the INSERT statement.
	 * @return int
	 */
	public function getInsertId(){
		return @sqlite_last_insert_rowid($this->resource);
	}


	/**
	 * Randomizes result order.
	 * @param BaseStatement $statement
	 */
	public function randomizeOrder(BaseStatement $statement){
		$statement->order('RANDOM()');
	}


	/**
	 * Returns the number of rows in the given result set.
	 * @param resource $resultSet
	 * @return int|bool
	 * @throws DriverException
	 */
	public function getNumRows($resultSet){
		if($this->unbuffered)
			throw new DriverException('Cannot count rows on unbuffered result.');
		return @sqlite_num_rows($resultSet);
	}


	/**
	 * Returns the number of affected rows in previous operation.
	 * @return int
	 */
	public function getAffectedRows(){
		return $this->affectedRows;
	}


	/**
	 * Escapes given value.
	 * @param mixed $value
	 * @param string $type
	 * @return mixed
	 * @throws InvalidArgumentException
	 */
	public function escape($value, $type){
		switch($type){
			case Manager::BOOL:
				return $value ? 1 : 0;

			case Manager::TEXT:
			case Manager::BINARY:
				return "'" . sqlite_escape_string($value) . "'";

			case Manager::IDENTIFIER:
				return str_replace('[*]', '*', '[' . str_replace('.', '].[', $value) . ']');

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
	public function unescape($value, $type){
		if($type === Manager::BINARY)
			return $value;
		throw new InvalidArgumentException('Unsupported data type.');
	}


	/**
	 * Returns the PRIMARY KEY column for given table.
	 * @param string $table
	 * @return string
	 */
	public function getPrimaryKey($table){
		$key = '';
		$pos = strpos($table, '.');
		if($pos !== false)
			$table = substr($table, $pos + 1);
		if(isset($this->tblData[$table]))
			$sql = $this->tblData[$table];
		else{
			$q = $this->runQuery("SELECT sql FROM sqlite_master WHERE tbl_name='$table'");
			$r = $this->fetch($q);
			if($r === false)
				return '';
			$this->tblData[$table] = $sql = $r['sql'];
		}

		$sql = explode("\n", $sql);
		foreach($sql as $field){
			$field = trim($field);
			if(stripos($field, 'PRIMARY KEY') !== false && $key === '')
				$key = preg_replace('~^"(\w+)".*$~i', '$1', $field);
		}
		return $key;
	}


	/**
	 * Returns types of columns in given result set.
	 * @param resource $resultSet
	 * @param string $table
	 * @return array
	 */
	public function getColumnTypes($resultSet, $table){
		if($table === null)
			return array();
		if(isset($this->tblData[$table]))
			$sql = $this->tblData[$table];
		else
			$q = $this->runQuery("SELECT sql FROM sqlite_master WHERE tbl_name='$table'");
		$r = $this->fetch($q);
		if($r === false)
			return array();
		$this->tblData[$table] = $sql = $r['sql'];
		$sql = explode("\n", $sql);

		$cols = array();
		foreach($sql as $field){
			$field = trim($field);
			preg_match('~^"(\w+)"\s+(integer|real|numeric|text|blob).+$~i', $field, $m);
			if(isset($m[1], $m[2]))
				$cols[$m[1]] = $m[2];
		}
		return $cols;
	}


	/**
	 * Parses UPDATE statement.
	 * @return string
	 */
	protected function parseUpdateStmt(){
		$sql = parent::parseUpdateStmt();
		return $this->updateLimit ? $this->applyLimit($sql . $this->clauses[3]) : $sql;
	}


	/**
	 * Parses DELETE statement.
	 * @return string
	 */
	protected function parseDeleteStmt(){
		$sql = parent::parseDeleteStmt();
		return $this->updateLimit ? $this->applyLimit($sql . $this->clauses[3]) : $sql;
	}


}
