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
use Neevo\DriverException;
use Neevo\DriverInterface;
use Neevo\Manager;
use Neevo\Parser;


/**
 * Neevo MySQL driver (PHP extension 'mysql')
 *
 * Driver configuration:
 * - host => MySQL server name or address
 * - port (int) => MySQL server port
 * - username
 * - password
 * - database => database to select
 * - charset => Character encoding to set (defaults to utf8)
 * - persistent (bool) => Try to find a persistent link
 * - unbuffered (bool) => Sends query without fetching and buffering the result
 *
 * - resource (type resource) => Existing MySQL link
 * - lazy, table_prefix... => see {@see Neevo\Connection}
 *
 * @author Smasty
 */
class MySQLDriver extends Parser implements DriverInterface {


	/** @var resource */
	private $resource;

	/** @var bool */
	private $unbuffered;

	/** @var int */
	private $affectedRows;


	/**
	 * Checks for required PHP extension.
	 * @throws DriverException
	 */
	public function __construct(BaseStatement $statement = null){
		if(!extension_loaded("mysql"))
			throw new DriverException("Cannot instantiate Neevo MySQL driver - PHP extension 'mysql' not loaded.");
		if($statement instanceof BaseStatement)
			parent::__construct($statement);
	}


	/**
	 * Creates connection to database.
	 * @param array $config Configuration options
	 * @throws DriverException
	 */
	public function connect(array $config){

		// Defaults
		$defaults = array(
			'resource' => null,
			'charset' => 'utf8',
			'username' => ini_get('mysql.default_user'),
			'password' => ini_get('mysql.default_password'),
			'host' => ini_get('mysql.default_host'),
			'port' => ini_get('mysql.default_port'),
			'persistent' => false,
			'unbuffered' => false
		);

		$config += $defaults;
		if(isset($config['port']))
			$host = $config['host'] . ':' . $config['port'];
		else
			$host = $config['host'];

		// Connect
		if(is_resource($config['resource']))
			$connection = $config['resource'];
		elseif($config['persistent'])
			$connection = @mysql_pconnect($host, $config['username'], $config['password']);
		else
			$connection = @mysql_connect($host, $config['username'], $config['password']);

		if(!is_resource($connection))
			throw new DriverException("Connection to host '$host' failed.");

		// Select DB
		if($config['database']){
			$db = mysql_select_db($config['database']);
			if(!$db)
				throw new DriverException("Could not select database '$config[database]'.");
		}

		$this->resource = $connection;

		//Set charset
		if(is_resource($connection)){
			if(function_exists('mysql_set_charset'))
				@mysql_set_charset($config['charset'], $connection);
			else
				$this->runQuery("SET NAMES " . $config['charset']);
		}

		$this->unbuffered = $config['unbuffered'];
	}


	/**
	 * Closes the connection.
	 */
	public function closeConnection(){
		@mysql_close($this->resource);
	}


	/**
	 * Frees memory used by given result set.
	 * @param resource $resultSet
	 * @return bool
	 */
	public function freeResultSet($resultSet){
		return @mysql_free_result($resultSet);
	}


	/**
	 * Executes given SQL statement.
	 * @param string $queryString
	 * @return resource|bool
	 * @throws DriverException
	 */
	public function runQuery($queryString){

		$this->affectedRows = false;
		if($this->unbuffered)
			$result = @mysql_unbuffered_query($queryString, $this->resource);
		else
			$result = @mysql_query($queryString, $this->resource);

		$error = str_replace('You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use', 'Syntax error', @mysql_error($this->resource));
		if($error && $result === false)
			throw new DriverException($error, @mysql_errno($this->resource), $queryString);

		$this->affectedRows = @mysql_affected_rows($this->resource);
		return $result;
	}


	/**
	 * Begins a transaction if supported.
	 * @param string $savepoint
	 */
	public function beginTransaction($savepoint = null){
		$this->runQuery($savepoint ? "SAVEPOINT $savepoint" : 'START TRANSACTION');
	}


	/**
	 * Commits statements in a transaction.
	 * @param string $savepoint
	 */
	public function commit($savepoint = null){
		$this->runQuery($savepoint ? "RELEASE SAVEPOINT $savepoint" : 'COMMIT');
	}


	/**
	 * Rollback changes in a transaction.
	 * @param string $savepoint
	 */
	public function rollback($savepoint = null){
		$this->runQuery($savepoint ? "ROLLBACK TO SAVEPOINT $savepoint" : 'ROLLBACK');
	}


	/**
	 * Fetches row from given result set as an associative array.
	 * @param resource $resultSet
	 * @return array
	 */
	public function fetch($resultSet){
		return @mysql_fetch_assoc($resultSet);
	}


	/**
	 * Moves internal result pointer.
	 * @param resource $resultSet
	 * @param int $offset
	 * @return bool
	 * @throws DriverException
	 */
	public function seek($resultSet, $offset){
		if($this->unbuffered){
			throw new DriverException('Cannot seek on unbuffered result.');
		}
		return @mysql_data_seek($resultSet, $offset);
	}


	/**
	 * Returns the ID generated in the INSERT statement.
	 * @return int
	 */
	public function getInsertId(){
		return @mysql_insert_id($this->resource);
	}


	/**
	 * Randomizes result order.
	 * @param BaseStatement $statement
	 */
	public function randomizeOrder(BaseStatement $statement){
		$statement->order('RAND()');
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
		return @mysql_num_rows($resultSet);
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
				return "'" . mysql_real_escape_string($value, $this->resource) . "'";

			case Manager::IDENTIFIER:
				return str_replace('`*`', '*', '`' . str_replace('.', '`.`', str_replace('`', '``', $value)) . '`');

			case Manager::BINARY:
				return "_binary'" . mysql_real_escape_string($value, $this->resource) . "'";

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
		$q = $this->runQuery('SHOW FULL COLUMNS FROM ' . $table);
		while($col = $this->fetch($q)){
			if(strtolower($col['Key']) === 'pri' && $key === '')
				$key = $col['Field'];
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
		$cols = array();
		$count = mysql_num_fields($resultSet);
		for($i = 0; $i < $count; $i++){
			$field = mysql_fetch_field($resultSet, $i);
			$cols[$field->name] = $field->type;
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
