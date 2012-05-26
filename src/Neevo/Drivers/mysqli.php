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

use Neevo,
	Neevo\DriverException;


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
class MySQLiDriver extends Neevo\Parser implements Neevo\IDriver {


	/** @var mysqli_result */
	private $resource;

	/** @var bool */
	private $unbuffered;

	/** @var int */
	private $affectedRows;


	/**
	 * Checks for required PHP extension.
	 * @return void
	 * @throws DriverException
	 */
	public function __construct(Neevo\BaseStatement $statement = null){
		if(!extension_loaded("mysqli"))
			throw new DriverException("Cannot instantiate Neevo MySQLi driver - PHP extension 'mysqli' not loaded.");
		if($statement instanceof Neevo\BaseStatement)
			parent::__construct($statement);
	}


	/**
	 * Creates connection to database.
	 * @param array $config Configuration options
	 * @return void
	 * @throws DriverException
	 */
	public function connect(array $config){

		// Defaults
		$defaults = array(
			'resource' => null,
			'charset' => 'utf8',
			'username' => ini_get('mysqli.default_user'),
			'password' => ini_get('mysqli.default_pw'),
			'socket' => ini_get('mysqli.default_socket'),
			'port' => ini_get('mysqli.default_port'),
			'host' => ini_get('mysqli.default_host'),
			'persistent' => false,
			'unbuffered' => false
		);

		$config += $defaults;

		// Connect
		if($config['resource'] instanceof \mysqli)
			$this->resource = $config['resource'];
		else
			$this->resource = new \mysqli($config['host'], $config['username'], $config['password'], $config['database'], $config['port'], $config['socket']);

		if($this->resource->connect_errno)
			throw new DriverException($this->resource->connect_error, $this->resource->connect_errno);

		// Set charset
		if($this->resource instanceof \mysqli){
			$ok = @$this->resource->set_charset($config['charset']);
			if(!$ok)
				$this->runQuery("SET NAMES " . $config['charset']);
		}

		$this->unbuffered = $config['unbuffered'];
	}


	/**
	 * Closes the connection.
	 * @return void
	 */
	public function closeConnection(){
		@$this->resource->close();
	}


	/**
	 * Frees memory used by given result set.
	 * @param mysqli_result $resultSet
	 * @return bool
	 */
	public function freeResultSet($resultSet){
		return true;
	}


	/**
	 * Executes given SQL statement.
	 * @param string $queryString
	 * @return mysqli_result|bool
	 * @throws DriverException
	 */
	public function runQuery($queryString){

		$this->affectedRows = false;
		$result = $this->resource->query($queryString, $this->unbuffered ? MYSQLI_USE_RESULT : MYSQLI_STORE_RESULT);

		$error = str_replace('You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use', 'Syntax error', $this->resource->error);
		if($error && $result === false)
			throw new DriverException($error, $this->resource->errno, $queryString);

		$this->affectedRows = $this->resource->affected_rows;
		return $result;
	}


	/**
	 * Begins a transaction if supported.
	 * @param string $savepoint
	 * @return void
	 */
	public function beginTransaction($savepoint = null){
		$this->runQuery($savepoint ? "SAVEPOINT $savepoint" : 'START TRANSACTION');
	}


	/**
	 * Commits statements in a transaction.
	 * @param string $savepoint
	 * @return void
	 */
	public function commit($savepoint = null){
		$this->runQuery($savepoint ? "RELEASE SAVEPOINT $savepoint" : 'COMMIT');
	}


	/**
	 * Rollbacks changes in a transaction.
	 * @param string $savepoint
	 * @return void
	 */
	public function rollback($savepoint = null){
		$this->runQuery($savepoint ? "ROLLBACK TO SAVEPOINT $savepoint" : 'ROLLBACK');
	}


	/**
	 * Fetches row from given result set as an associative array.
	 * @param mysqli_result $resultSet
	 * @return array
	 */
	public function fetch($resultSet){
		return $resultSet->fetch_assoc();
	}


	/**
	 * Moves internal result pointer.
	 * @param mysqli_result $resultSet
	 * @param int
	 * @return bool
	 * @throws DriverException
	 */
	public function seek($resultSet, $offset){
		if($this->unbuffered)
			throw new DriverException('Cannot seek on unbuffered result.');
		return $resultSet->data_seek($offset);
	}


	/**
	 * Returns the ID generated in the INSERT statement.
	 * @return int
	 */
	public function getInsertId(){
		return $this->resource->insert_id;
	}


	/**
	 * Randomizes result order.
	 * @param Neevo\BaseStatement $statement
	 * @return void
	 */
	public function randomizeOrder(Neevo\BaseStatement $statement){
		$statement->order('RAND()');
	}


	/**
	 * Returns the number of rows in the given result set.
	 * @param \mysqli_result $resultSet
	 * @return int|FALSE
	 * @throws DriverException
	 */
	public function getNumRows($resultSet){
		if($this->unbuffered)
			throw new DriverException('Cannot count rows on unbuffered result.');
		if($resultSet instanceof \mysqli_result)
			return $resultSet->num_rows;
		return false;
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
	 * @throws \InvalidArgumentException
	 */
	public function escape($value, $type){
		switch($type){
			case Neevo\Manager::BOOL:
				return $value ? 1 : 0;

			case Neevo\Manager::TEXT:
				return "'" . $this->resource->real_escape_string($value) . "'";

			case Neevo\Manager::IDENTIFIER:
				return str_replace('`*`', '*', '`' . str_replace('.', '`.`', str_replace('`', '``', $value)) . '`');

			case Neevo\Manager::BINARY:
				return "_binary'" . mysqli_real_escape_string($this->resource, $value) . "'";

			case Neevo\Manager::DATETIME:
				return ($value instanceof \DateTime) ? $value->format("'Y-m-d H:i:s'") : date("'Y-m-d H:i:s'", $value);

			default:
				throw new \InvalidArgumentException('Unsupported data type.');
				break;
		}
	}


	/**
	 * Decodes given value.
	 * @param mixed $value
	 * @param string $type
	 * @return mixed
	 * @throws \InvalidArgumentException
	 */
	public function unescape($value, $type){
		if($type === Neevo\Manager::BINARY)
			return $value;
		throw new \InvalidArgumentException('Unsupported data type.');
	}


	/**
	 * Returns the PRIMARY KEY column for given table.
	 * @param string $table
	 * @return string
	 */
	public function getPrimaryKey($table){
		$key = '';
		$q = $this->runQuery('SHOW FULL COLUMNS FROM ' . $table);
		while($col = $this->fetch($q)){
			if(strtolower($col['Key']) === 'pri' && $key === '')
				$key = $col['Field'];
		}
		return $key;
	}


	/**
	 * Returns types of columns in given result set.
	 * @param mysqli_result $resultset
	 * @param string $table
	 * @return array
	 */
	public function getColumnTypes($resultSet, $table){
		static $colTypes;
		if(empty($colTypes)){
			$constants = get_defined_constants(true);
			foreach($constants['mysqli'] as $type => $code){
				if(strncmp($type, 'MYSQLI_TYPE_', 12) === 0)
					$colTypes[$code] = strtolower(substr($type, 12));
			}
			$colTypes[MYSQLI_TYPE_LONG] = $colTypes[MYSQLI_TYPE_SHORT] = $colTypes[MYSQLI_TYPE_TINY] = 'int';
		}

		$cols = array();
		while($field = $resultSet->fetch_field()){
			$cols[$field->name] = $colTypes[$field->type];
		}
		return $cols;
	}


	/**
	 * Parses UPDATE statement.
	 * @return string
	 */
	protected function parseUpdateStmt(){
		$sql = parent::parseUpdateStmt();
		return $this->applyLimit($sql . $this->clauses[3]);
	}


	/**
	 * Parses DELETE statement.
	 * @return string
	 */
	protected function parseDeleteStmt(){
		$sql = parent::parseDeleteStmt();
		return $this->applyLimit($sql . $this->clauses[3]);
	}


}
