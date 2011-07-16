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
 * Neevo SQLite 3 driver (PHP extension 'sqlite3')
 *
 * Driver configuration:
 *  - database (or file)
 *  - charset => Character encoding to set (defaults to utf-8)
 *  - dbcharset => Database character encoding (will be converted to 'charset')
 *
 *  - updateLimit (bool) => Set TRUE if SQLite driver was compiled with SQLITE_ENABLE_UPDATE_DELETE_LIMIT
 *  - resource (instance of SQLite3) => Existing SQLite 3 link
 *  - lazy, table_prefix => see NeevoConnection
 *
 * Since SQLite 3 only allows unbuffered queries, number of result rows and seeking
 * is not supported for this driver.
 *
 * @author Martin Srank
 * @package Neevo\Drivers
 */
class NeevoDriverSQLite3 extends NeevoParser implements INeevoDriver {


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
	 * Check for required PHP extension.
	 * @return void
	 * @throws NeevoDriverException
	 */
	public function __construct(NeevoBaseStmt $statement = null){
		if(!extension_loaded("sqlite3"))
			throw new NeevoDriverException("Cannot instantiate Neevo SQLite 3 driver - PHP extension 'sqlite3' not loaded.");
		if($statement instanceof NeevoBaseStmt)
			parent::__construct($statement);
	}


	/**
	 * Create connection to database.
	 * @param array $config Configuration options
	 * @return void
	 * @throws NeevoException
	 */
	public function connect(array $config){
		NeevoConnection::alias($config, 'database', 'file');
		NeevoConnection::alias($config, 'updateLimit', 'update_limit');

		$defaults = array(
			'resource' => null,
			'updateLimit' => false,
			'charset' => 'UTF-8',
			'dbcharset' => 'UTF-8'
		);

		$config += $defaults;

		// Connect
		if($config['resource'] instanceof SQLite3)
			$connection = $config['resource'];
		else{
			try{
				$connection = new SQLite3($config['database']);
			} catch(Exception $e){
					throw new NeevoException($e->getMessage(), $e->getCode());
			}
		}

		if(!($connection instanceof SQLite3))
			throw new NeevoException("Opening database file '$config[database]' failed.");

		$this->resource = $connection;
		$this->updateLimit = (bool) $config['updateLimit'];

		// Set charset
		$this->dbCharset = $config['dbcharset'];
		$this->charset = $config['charset'];
		if(strcasecmp($this->dbCharset, $this->charset) === 0)
			$this->dbCharset = $this->charset = null;
	}


	/**
	 * Close the connection.
	 * @return void
	 */
	public function closeConnection(){
		$this->resource->close();
	}



	/**
	 * Free memory used by given result.
	 *
	 * NeevoResult automatically NULLs the resource, so this is not necessary.
	 * @param SQLite3Result $resultSet
	 * @return bool
	 */
	public function freeResultSet($resultSet){
		return true;
	}


	/**
	 * Execute given SQL statement.
	 * @param string $queryString
	 * @return SQLite3Result|bool
	 * @throws NeevoException
	 */
	public function runQuery($queryString){

		$this->affectedRows = false;
		if($this->dbCharset !== null)
			$queryString = iconv($this->charset, $this->dbCharset . '//IGNORE', $queryString);

		$result = $this->resource->query($queryString);

		if($result === false)
			throw new NeevoException($this->resource->lastErrorMsg(), $this->resource->lastErrorCode(), $queryString);

		$this->affectedRows = $this->resource->changes();
		return $result;
	}


	/**
	 * Begin a transaction if supported.
	 * @param string $savepoint
	 * @return void
	 */
	public function beginTransaction($savepoint = null){
		$this->runQuery($savepoint ? "SAVEPOINT $savepoint" : 'BEGIN');
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
	 * @param SQLite3Result $resultSet
	 * @return array
	 */
	public function fetch($resultSet){
		$row = $resultSet->fetchArray(SQLITE3_ASSOC);
		$charset = $this->charset === null ? null : $this->charset.'//TRANSLIT';

		if($row){
			$fields = array();
			foreach($row as $key=>$val){
				if($charset !== null && is_string($val))
					$val = iconv($this->dbcharset, $charset, $val);
				$fields[str_replace(array('[', ']'), '', $key)] = $val;
			}
			return $fields;
		}
		return $row;
	}


	/**
	 * Move internal result pointer.
	 *
	 * Not supported because of unbuffered queries.
	 * @param SQLite3Result $resultSet
	 * @param int $offset
	 * @return bool
	 * @throws NeevoDriverException
	 */
	public function seek($resultSet, $offset){
		throw new NeevoDriverException('Cannot seek on unbuffered result.');
	}


	/**
	 * Get the ID generated in the INSERT statement.
	 * @return int
	 */
	public function getInsertId(){
		return $this->resource->lastInsertRowID();
	}


	/**
	 * Randomize result order.
	 * @param NeevoBaseStmt $tatement
	 * @return void
	 */
	public function randomizeOrder(NeevoBaseStmt $statement){
		$statement->order('RANDOM()');
	}


	/**
	 * Get the number of rows in the given result set.
	 *
	 * Not supported because of unbuffered queries.
	 * @param SQLite3Result $resultSet
	 * @return int|FALSE
	 * @throws NeevoDriverException
	 */
	public function getNumRows($resultSet){
		throw new NeevoDriverException('Cannot count rows on unbuffered result.');
	}


	/**
	 * Get the umber of affected rows in previous operation.
	 * @return int
	 */
	public function getAffectedRows(){
		return $this->affectedRows;
	}


	/**
	 * Escape given value.
	 * @param mixed $value
	 * @param string $type
	 * @throws InvalidArgumentException
	 * @return mixed
	 */
	public function escape($value, $type){
		switch($type){
			case Neevo::BOOL:
				return $value ? 1 :0;

			case Neevo::TEXT:
				return "'". $this->resource->escapeString($value) ."'";

			case Neevo::IDENTIFIER:
				return str_replace('[*]', '*', '[' . str_replace('.', '].[', $value) . ']');

			case Neevo::BINARY:
				return "X'" . bin2hex((string) $value) . "'";

			case Neevo::DATETIME:
				return ($value instanceof DateTime) ? $value->format("'Y-m-d H:i:s'") : date("'Y-m-d H:i:s'", $value);

			default:
				throw new InvalidArgumentException('Unsupported data type');
				break;
		}
	}


	/**
	 * Decode given value.
	 * @param mixed $value
	 * @param string $type
	 * @return mixed
	 */
	public function unescape($value, $type){
		if($type === Neevo::BINARY)
			return $value;
		throw new InvalidArgumentException('Unsupported data type.');
	}


	/**
	 * Get the PRIMARY KEY column for given table.
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
	 * Get types of columns in given result set.
	 * @param SQLite3Result $resultSet
	 * @param string $table
	 * @return array
	 */
	public function getColumnTypes($resultSet, $table){
		if($table === null)
			return array();
		if(isset($this->tblData[$table]))
			$sql = $this->tblData[$table];
		else{
			$q = $this->runQuery("SELECT sql FROM sqlite_master WHERE tbl_name='$table'");
			$r = $this->fetch($q);
			if($r === false){
				return array();
			}
			$this->tblData[$table] = $sql = $r['sql'];
		}
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


	/*  ************  NeevoParser overrides  ************  */


	/**
	 * Parse UPDATE statement.
	 * @return string
	 */
	protected function parseUpdateStmt(){
		$sql = parent::parseUpdateStmt();
		return $this->updateLimit ? $this->applyLimit($sql . $this->clauses[3]) : $sql;
	}


	/**
	 * Parse DELETE statement.
	 * @return string
	 */
	protected function parseDeleteStmt(){
		$sql = parent::parseDeleteStmt();
		return $this->updateLimit ? $this->applyLimit($sql . $this->clauses[3]) : $sql;
	}


}
