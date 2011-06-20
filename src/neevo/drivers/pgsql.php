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
 * Neevo PostgreSQL driver (PHP extension 'pgsql')
 *
 * Driver configuration:
 *  - host, hostaddr, port, dbname, user, password, connect_timeout, options, sslmode, service => see PHP pg_connect()
 *  - string => Use connection string instead
 *  - schema => Schema search path
 *  - charset => Character encoding to set (defaults to utf-8)
 *  - persistent (bool) => Try to find a persistent link
 *
 *  - resource (type resource) => Existing SQLite link
 *  - lazy, table_prefix... => see NeevoConnection
 *
 * @author Martin Srank
 * @package Neevo\Drivers
 */
class NeevoDriverPgSQL implements INeevoDriver {


	/** @var resource */
	private $resource;

	/** @var int */
	private $affectedRows;

	/** @var bool */
	private $escapeMethod = false;


	/**
	 * Check for required PHP extension.
	 * @return void
	 * @throws NeevoDriverException
	 */
	public function __construct(NeevoStmtBase $statement = null){
		if(!extension_loaded("pgsql"))
			throw new NeevoDriverException("Cannot instantiate Neevo PgSQL driver - PHP extension 'pgsql' not loaded.");
		if($statement instanceof NeevoStmtBase)
			parent::__construct($statement);
	}


	/**
	 * Create connection to database.
	 * @param array $config Configuration options
	 * @return void
	 * @throws NeevoException
	 */
	public function connect(array $config){

		$defaults = array(
			'resource' => null,
			'persistent' => false,
			'charset' => 'utf8'
		);

		$config += $defaults;

		if(isset($config['string']))
			$string = $config['string'];
		else{
			// String generation
			$string = '';
			foreach(array('host', 'hostaddr', 'port', 'dbname', 'user', 'password', 'connect_timeout', 'options', 'sslmode', 'service') as $cfg){
				if(isset($config[$cfg]))
					$string .= "$cfg=$config[$cfg] ";
			}
		}

		// Connect
		if(is_resource($config['resource']))
			$connection = $config['resource'];
		elseif($config['persistent'])
			$connection = @pg_pconnect($string, PGSQL_CONNECT_FORCE_NEW);
		else
			$connection = @pg_connect($string, PGSQL_CONNECT_FORCE_NEW);

		if(!is_resource($connection))
			throw new NeevoException("Connection to database failed.");

		$this->resource = $connection;

		// Encoding
		@pg_set_client_encoding($this->resource, $config['charset']);

		// Schema
		 if(isset($config['schema']))
			 $this->runQuery('SET search_path TO "' . $config['schema'] . '"');

		 $this->escapeMethod = version_compare(PHP_VERSION , '5.2.0', '>=');
	}


	/**
	 * Close the connection.
	 * @return void
	 */
	public function closeConnection(){
		@pg_close($this->resource);
	}


	/**
	 * Free memory used by given result set.
	 * @param resource $resultSet
	 * @return bool
	 */
	public function freeResultSet($resultSet){
		@pg_free_result($resultSet);
	}


	/**
	 * Execute given SQL statement.
	 * @param string $queryString
	 * @return resource|bool
	 * @throws NeevoException
	 */
	public function runQuery($queryString){
		$this->affectedRows = false;

		$result = @pg_query($this->resource, $queryString);
		if($result === false)
			throw new NeevoException("Query failed. " . pg_last_error($this->resource), null, $queryString);

		$this->affectedRows = @pg_affected_rows($result);
		return $result;
	}


	/**
	 * Begin a transaction if supported.
	 * @param string $savepoint
	 * @return void
	 */
	public function beginTransaction($savepoint = null){
		$this->runQuery($savepoint ? "SAVEPOINT $savepoint" : 'START TRANSACTION');
	}


	/**
	 * Commit statements in a transaction.
	 * @param string $savepoint
	 * @return void
	 */
	public function commit($savepoint = null){
		$this->runQuery($savepoint ? "RELEASE SAVEPOINT $savepoint" : 'COMMIT');
	}


	/**
	 * Rollback changes in a transaction.
	 * @param string $savepoint
	 * @return void
	 */
	public function rollback($savepoint = null){
		$this->runQuery($savepoint ? "ROLLBACK TO SAVEPOINT $savepoint" : 'ROLLBACK');
	}


	/**
	 * Fetch row from given result set as an associative array.
	 * @param resource $resultSet
	 * @return array
	 */
	public function fetch($resultSet){
		return @pg_fetch_assoc($resultSet);
	}


	/**
	 * Move internal result pointer.
	 * @param resource $resultSet
	 * @param int $offset
	 * @return bool
	 */
	public function seek($resultSet, $offset){
		return @pg_result_seek($resultSet, $offset);
	}


	/**
	 * Get the ID generated in the INSERT statement.
	 * @return int
	 */
	public function getInsertId(){
		$result = $this->runQuery("SELECT LASTVAL()");
		if(!$result)
			return false;

		$r = $this->fetch($result);
		return is_array($r) ? reset($r) : false;
	}


	/**
	 * Randomize result order.
	 * @param NeevoStmtBase $statement
	 * @return void
	 */
	public function randomizeOrder(NeevoStmtBase $statement){
		$statement->order('RAND()');
	}


	/**
	 * Get the number of rows in the given result set.
	 * @param resource $resultSet
	 * @return int|FALSE
	 */
	public function getNumRows($resultSet){
		return @pg_num_rows($resultSet);
	}


	/**
	 * Get the number of affected rows in previous operation.
	 * @return int
	 */
	public function getAffectedRows(){
		return $this->affectedRows;
	}


	/**
	 * Escape given value.
	 * @param mixed $value
	 * @param string $type
	 * @return mixed
	 * @throws InvalidArgumentException
	 */
	public function escape($value, $type){
		switch($type){
			case Neevo::BOOL:
				return $value ? 'TRUE' : 'FALSE';

			case Neevo::TEXT:
				if($this->escapeMethod)
					return "'" . pg_escape_string($this->resource, $value) . "'";
				else
					return "'" . pg_escape_string($value) . "'";

			case Neevo::BINARY:
				if($this->escapeMethod)
					return "'" . pg_escape_bytea($this->resource, $value) . "'";
				else
					return "'" . pg_escape_bytea($value) . "'";

			case Neevo::IDENTIFIER:
				 return '"' . str_replace('.', '"."', str_replace('"', '""', $value)) . '"';

			case Neevo::DATETIME:
				return ($value instanceof DateTime) ? $value->format("'Y-m-d H:i:s'") : date("'Y-m-d H:i:s'", $value);

			default:
				throw new InvalidArgumentException('Unsupported data type.');
				break;
		}
	}


	/**
	 * Decode given value.
	 * @param mixed $value
	 * @param string $type
	 * @return mixed
	 * @throws InvalidArgumentException
	 */
	public function unescape($value, $type){
		if($type === Neevo::BINARY)
			return pg_unescape_bytea($value);
		throw new InvalidArgumentException('Unsupported data type.');
	}


	/**
	 * Get the PRIMARY KEY column for given table.
	 *
	 * Experimental implementation!
	 * @param string $table
	 * @return string
	 */
	public function getPrimaryKey($table){
		$def = $this->fetch(
			$this->runQuery("SELECT indexdef FROM pg_indexes WHERE indexname = '{$table}_pkey'")
		);
		$def = reset($def);
		if(preg_match("~{$table}_pkey\s+ON\s+{$table}.*\((\w+).*\)~i", $def, $matches))
			return $matches[1];
		return false;
	}


	/**
	 * Get types of columns in given result set.
	 * @param resource $resultSet
	 * @param string $table
	 * @return array
	 */
	public function getColumnTypes($resultSet, $table){
		if($table === null)
			return array();
		$cols = pg_meta_data($this->resource, $table);
		foreach($cols as $key => $value){
			$cols[$key] = preg_replace('~[^a-z]~i', '', $value['type']);
		}
		return $cols;
	}


}
