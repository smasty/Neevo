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
 * Neevo PDO driver (PHP extension 'pdo') Experimental!
 *
 * Driver configuration:
 * - dsn => Driver-specific DSN
 * - username
 * - password
 * - options (array) => Driver-specific options for {@see PDO::__construct}
 * - resource (instance of PDO) => Existing PDO connection
 * - lazy, table_prefix... => see {@see Neevo\Connection}
 *
 * @author Smasty
 */
class PDODriver extends Neevo\Parser implements Neevo\IDriver {


	/** @var \PDO */
	private $resource;

	/** @var string */
	private $driverName;

	/** @var int */
	private $affectedRows;


	/**
	 * Checks for required PHP extension.
	 * @throws DriverException
	 */
	public function __construct(Neevo\BaseStatement $statement = null){
		if(!extension_loaded("pdo"))
			throw new DriverException("Cannot instantiate Neevo PDO driver - PHP extension 'pdo' not loaded.");
		if($statement instanceof Neevo\BaseStatement)
			parent::__construct($statement);
	}


	/**
	 * Creates connection to database.
	 * @param array $config Configuration options
	 * @throws DriverException
	 */
	public function connect(array $config){
		Neevo\Connection::alias($config, 'resource', 'pdo');

		// Defaults
		$defaults = array(
			'dsn' => null,
			'resource' => null,
			'username' => null,
			'password' => null,
			'options' => array(),
		);

		$config += $defaults;

		// Connect
		if($config['resource'] instanceof \PDO)
			$this->resource = $config['resource'];
		else try{
			$this->resource = new \PDO($config['dsn'], $config['username'], $config['password'], $config['options']);
		} catch(\PDOException $e){
			throw new DriverException($e->getMessage(), $e->getCode());
		}

		if(!$this->resource)
			throw new DriverException('Connection failed.');

		$this->driverName = $this->resource->getAttribute(\PDO::ATTR_DRIVER_NAME);
	}


	/**
	 * Closes the connection.
	 */
	public function closeConnection(){
		@$this->resource = null;
	}


	/**
	 * Frees memory used by given result set.
	 * @param \PDOStatement $resultSet
	 * @return bool
	 */
	public function freeResultSet($resultSet){
		return $resultSet = null;
	}


	/**
	 * Executes given SQL statement.
	 * @param string $queryString
	 * @return \PDOStatement|bool
	 * @throws DriverException
	 */
	public function runQuery($queryString){

		$cmd = strtoupper(substr(trim($queryString), 0, 6));
		static $list = array('UPDATE' => 1, 'DELETE' => 1, 'INSERT' => 1, 'REPLAC' => 1);
		$this->affectedRows = false;

		if(isset($list[$cmd])){
			$this->affectedRows = $this->resource->exec($queryString);

			if($this->affectedRows === false){
				$error = $this->resource->errorInfo();
				throw new DriverException("SQLSTATE[$error[0]]: $error[2]", $error[1], $queryString);
			} else
				return true;
		}

		$result = $this->resource->query($queryString);

		if($result === false){
			$error = $this->resource->errorInfo();
			throw new DriverException("SQLSTATE[$error[0]]: $error[2]", $error[1], $queryString);
		}else
			return $result;
	}


	/**
	 * Begins a transaction if supported.
	 * @param string $savepoint
	 */
	public function beginTransaction($savepoint = null){
		if(!$this->resource->beginTransaction()){
			$error = $this->resource->errorInfo();
			throw new DriverException("SQLSTATE[$error[0]]: $error[2]", $error[1]);
		}
	}


	/**
	 * Commits statements in a transaction.
	 * @param string $savepoint
	 */
	public function commit($savepoint = null){
		if(!$this->resource->commit()){
			$error = $this->resource->errorInfo();
			throw new DriverException("SQLSTATE[$error[0]]: $error[2]", $error[1]);
		}
	}


	/**
	 * Rollbacks changes in a transaction.
	 * @param string $savepoint
	 */
	public function rollback($savepoint = null){
		if(!$this->resource->rollBack()){
			$error = $this->resource->errorInfo();
			throw new DriverException("SQLSTATE[$error[0]]: $error[2]", $error[1]);
		}
	}


	/**
	 * Fetches row from given result set as an associative array.
	 * @param \PDOStatement $resultSet
	 * @return array
	 */
	public function fetch($resultSet){
		return $resultSet->fetch(\PDO::FETCH_ASSOC);
	}


	/**
	 * Moves internal result pointer.
	 * @param mysqli_result $resultSet
	 * @param int
	 * @return bool
	 * @throws Neevo\ImplementationException
	 */
	public function seek($resultSet, $offset){
		throw new Neevo\ImplementationException('Cannot seek on unbuffered result.');
	}


	/**
	 * Returns the ID generated in the INSERT statement.
	 * @return int
	 */
	public function getInsertId(){
		return $this->resource->lastInsertId();
	}


	/**
	 * Randomizes result order.
	 * @param Neevo\BaseStatement $statement
	 */
	public function randomizeOrder(Neevo\BaseStatement $statement){
		switch($this->driverName){
			case 'mysql':
			case 'pgsql':
				$random = 'RAND()';

			case 'sqlite':
			case 'sqlite2':
				$random = 'RANDOM()';

			case 'odbc':
				$random = 'Rnd(id)';

			case 'oci':
				$random = 'dbms_random.value';

			case 'mssql':
				$random = 'NEWID()';
		}
		$statement->order($random);
	}


	/**
	 * Returns the number of rows in the given result set.
	 * @param \PDOStatement $resultSet
	 * @return int
	 */
	public function getNumRows($resultSet){
		$resultSet->rowCount();
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
				return $this->resource->quote($value, \PDO::PARAM_BOOL);

			case Neevo\Manager::TEXT:
				return $this->resource->quote($value, \PDO::PARAM_STR);

			case Neevo\Manager::IDENTIFIER:
				switch($this->driverName){
					case 'mysql':
						return str_replace('`*`', '*', '`' . str_replace('.', '`.`', str_replace('`', '``', $value)) . '`');

					case 'pgsql':
						return '"' . str_replace('.', '"."', str_replace('"', '""', $value)) . '"';

					case 'sqlite':
					case 'sqlite2':
						return str_replace('[*]', '*', '[' . str_replace('.', '].[', $value) . ']');

					case 'odbc':
					case 'oci':
					case 'mssql':
						return '[' . str_replace(array('[', ']'), array('[[', ']]'), $value) . ']';

					default:
						return $value;
				}

			case Neevo\Manager::BINARY:
				return $this->resource->quote($value, \PDO::PARAM_LOB);

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
	 *
	 * Not supported by all PDO drivers.
	 * @param string $table
	 * @throws Neevo\ImplementationException
	 */
	public function getPrimaryKey($table, $resultSet = null){
		if($resultSet instanceof \PDOStatement){
			$i = 0;
			while($col = $resultSet->getColumnMeta($i++)){
				if(in_array('primary_key', $col['flags']))
					$primaryKey = $col['name'];
			}
		}
		if(isset($primaryKey))
			return $primaryKey;
		throw new Neevo\ImplementationException;
	}


	/**
	 * Returns types of columns in given result set.
	 *
	 * Not supported by all PDO drivers.
	 * @param \PDOStatement $resultset
	 * @param string $table
	 * @throws Neevo\ImplementationException
	 */
	public function getColumnTypes($resultSet, $table){
		$types = array();
		$i = 0;
		while($col = $resultSet->getColumnMeta($i++))
			$types[$col['name']] = strtolower($col['native_type']);

		if(empty($types))
			throw new Neevo\ImplementationException;
		return $types;
	}


	/**
	 * Parses UPDATE statement.
	 * @return string
	 */
	protected function parseUpdateStmt(){
		$sql = parent::parseUpdateStmt();
		if($this->driverName === 'mysql')
			return $this->applyLimit($sql . $this->clauses[3]);
		return $sql;
	}


	/**
	 * Parses DELETE statement.
	 * @return string
	 */
	protected function parseDeleteStmt(){
		$sql = parent::parseDeleteStmt();
		if($this->driverName === 'mysql')
			return $this->applyLimit($sql . $this->clauses[3]);
		return $sql;
	}


	/**
	 * Applies LIMIT/OFFSET to SQL command.
	 * @param string $sql SQL command
	 * @return string
	 * @throws DriverException
	 */
	protected function applyLimit($sql){
		list($limit, $offset) = $this->stmt->getLimit();
		if((int) $limit < 1 && (int) $offset < 1)
			return $sql;

		switch($this->stmt->getConnection()->getDriver()->getDriverName()){
			case 'mysql':
			case 'pgsql':
			case 'sqlite':
			case 'sqlite2':
				if((int) $limit > 0){
					$sql .= "\nLIMIT " . (int) $limit;
					if((int) $offset > 0)
					$sql .= ' OFFSET ' . (int) $offset;
				}
				return $sql;

			case 'odbc':
			case 'mssql':
				if($offset < 1)
					return 'SELECT TOP ' . (int) $limit . " *\nFROM (\n\t"
						 . implode("\n\t", explode("\n", $sql)) . "\n)";

			default:
				throw new DriverException('PDO or selected driver does not allow apllying limit or offset.');
		}
	}


	/**
	 * Returns PDO instance.
	 * @return \PDO
	 */
	public function getResource(){
		return $this->resource;
	}


	/**
	 * Returns PDO driver name.
	 * @return string
	 */
	public function getDriverName(){
		return $this->driverName;
	}


}
