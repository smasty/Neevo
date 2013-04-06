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

namespace Neevo;

use Countable;
use DateTime;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;


/**
 * Represents a result. Can be iterated, counted and provides fluent interface.
 *
 * @method Result as($alias)
 *
 * @author Smasty
 */
class Result extends BaseStatement implements IteratorAggregate, Countable {


	/** @var string */
	protected $grouping;

	/** @var array */
	protected $columns = array();

	/** @var array */
	protected $joins;

	/** @var string */
	protected $tableAlias;

	/** @var resource */
	protected $resultSet;

	/** @var array */
	protected $columnTypes = array();

	/** @var bool */
	protected $detectTypes;

	/** @var string */
	private $rowClass = 'Neevo\\Row';

	/** @var ResultIterator */
	private $iterator;


	/**
	 * Creates SELECT statement.
	 * @param Connection $connection
	 * @param string|array|Traversable $columns
	 * @param string|Result $source Table name or subquery
	 * @throws InvalidArgumentException
	 */
	public function __construct(Connection $connection, $columns = null, $source = null){
		parent::__construct($connection);

		// Input check
		if($columns === null && $source === null)
			throw new InvalidArgumentException('Missing select source.');
		if($source === null){
			$source = $columns;
			$columns = '*';
		}
		if(!is_string($source) && !$source instanceof self)
			throw new InvalidArgumentException('Source must be a string or Neevo\\Result.');

		$columns = is_string($columns)
			? explode(',', $columns) : ($columns instanceof Traversable
				? iterator_to_array($columns) : (array) $columns);

		if(empty($columns))
			throw new InvalidArgumentException('No columns given.');

		if($source instanceof self)
			$this->subqueries[] = $source;

		$this->type = Manager::STMT_SELECT;
		$this->columns = array_map('trim', $columns);
		$this->source = $source;
		$this->detectTypes = (bool) $connection['result']['detectTypes'];
		$this->setRowClass($connection['rowClass']);
	}


	/**
	 * Destroys the result set resource and free memory.
	 */
	public function __destruct(){
		try{
			$this->connection->getDriver()->freeResultSet($this->resultSet);
		} catch(ImplementationException $e){}

		$this->resultSet = null;
	}


	public function __call($name, $args){
		$name = strtolower($name);
		if($name === 'as')
			return $this->setAlias(isset($args[0]) ? $args[0] : null);
		return parent::__call($name, $args);
	}


	/**
	 * Defines grouping rule.
	 * @param string $rule
	 * @param string $having Optional
	 * @return Result fluent interface
	 */
	public function group($rule, $having = null){
		if($this->validateConditions())
			return $this;

		$this->resetState();
		$this->grouping = array($rule, $having);
		return $this;
	}


	/**
	 * Performs JOIN on tables.
	 * @param string|Result|Literal $source Table name or subquery
	 * @param string|Literal $condition
	 * @return Result fluent interface
	 */
	public function join($source, $condition){
		if($this->validateConditions())
			return $this;

		if(!(is_string($source) || $source instanceof self || $source instanceof Literal))
			throw new InvalidArgumentException('Source must be a string, Neevo\\Literal or Neevo\\Result.');
		if(!(is_string($condition) || $condition instanceof Literal))
			throw new InvalidArgumentException('Condition must be a string or Neevo\\Literal.');

		if($source instanceof self)
			$this->subqueries[] = $source;

		$this->resetState();
		$type = (func_num_args() > 2) ? func_get_arg(2) : '';

		$this->joins[] = array($source, $condition, $type);

		return $this;
	}


	/**
	 * Performs LEFT JOIN on tables.
	 * @param string|Result|Literal $source Table name or subquery
	 * @param string|Literal $condition
	 * @return Result fluent interface
	 */
	public function leftJoin($source, $condition){
		return $this->join($source, $condition, Manager::JOIN_LEFT);
	}


	/**
	 * Performs INNER JOIN on tables.
	 * @param string|Result|Literal $source Table name or subquery
	 * @param string|Literal $condition
	 * @return Result fluent interface
	 */
	public function innerJoin($source, $condition){
		return $this->join($source, $condition, Manager::JOIN_INNER);
	}


	/**
	 * Adjusts the LIMIT and OFFSET clauses according to defined page number and number of items per page.
	 * @param int $page Page number
	 * @param int $items Number of items per page
	 * @return Result fluent interface
	 * @throws InvalidArgumentException
	 */
	public function page($page, $items){
		if($page < 1 || $items < 1)
			throw new InvalidArgumentException('Both arguments must be positive integers.');
		return $this->limit((int) $items, (int) ($items * --$page));
	}


	/**
	 * Fetches the row on current position.
	 * @return Row|bool
	 */
	public function fetch(){
		$this->performed || $this->run();

		$row = $this->connection->getDriver()->fetch($this->resultSet);
		if(!is_array($row))
			return false;

		// Type converting
		if($this->detectTypes)
			$this->detectTypes();
		if(!empty($this->columnTypes)){
			foreach($this->columnTypes as $col => $type){
				if(isset($row[$col]))
					$row[$col] = $this->convertType($row[$col], $type);
			}
		}
		return new $this->rowClass($row, $this);
	}


	/**
	 * Fetches all rows in result set.
	 * @param int $limit Limit number of returned rows
	 * @param int $offset Seek to offset (fails on unbuffered results)
	 * @return Row[]
	 */
	public function fetchAll($limit = null, $offset = null){
		$limit = $limit === null ? -1 : (int) $limit;
		if($offset !== null)
			$this->seek((int) $offset);

		$row = $this->fetch();
		if(!$row)
			return array();

		$rows = array();
		do{
			if($limit === 0)
				break;
			$rows[] = $row;
			$limit--;
		} while($row = $this->fetch());

		return $rows;
	}


	/**
	 * Fetches the first value from current row.
	 * @return mixed
	 */
	public function fetchSingle(){
		$this->performed || $this->run();
		$row = $this->connection->getDriver()->fetch($this->resultSet);

		if(!$row)
			return false;
		$value = reset($row);

		// Type converting
		if($this->detectTypes)
			$this->detectTypes();
		if(!empty($this->columnTypes)){
			$key = key($row);
			if(isset($this->columnTypes[$key]))
				$value = $this->convertType($value, $this->columnTypes[$key]);
		}

		return $value;
	}


	/**
	 * Fetches rows as $key=>$value pairs.
	 * @param string $key Key column
	 * @param string $value Value column. NULL for whole row.
	 * @return Row[]
	 */
	public function fetchPairs($key, $value = null){
		$clone = clone $this;

		// If executed w/o needed cols, force exec w/ them.
		if(!in_array('*', $clone->columns)){
			if($value !== null)
				$clone->columns = array($key, $value);
			elseif(!in_array($key, $clone->columns))
				$clone->columns[] = $key;
		}
		$k = substr($key, ($pk = strrpos($key, '.')) ? $pk+1 : 0);
		$v = $value === null ? null : substr($value, ($pv = strrpos($value, '.')) ? $pv+1 : 0);

		$rows = array();
		while($row = $clone->fetch()){
			if(!$row)
				return array();
			$rows[$row[$k]] = $value === null ? $row : $row->$v;
		}

		return $rows;
	}


	/**
	 * Moves internal result pointer.
	 * @param int $offset
	 * @return bool
	 * @throws NeevoException
	 */
	public function seek($offset){
		$this->performed || $this->run();
		$seek = $this->connection->getDriver()->seek($this->resultSet, $offset);
		if($seek)
			return $seek;
		throw new NeevoException("Cannot seek to offset $offset.");
	}


	public function rows(){
		return $this->count();
	}


	/**
	 * Counts number of rows.
	 * @param string $column
	 * @return int
	 * @throws DriverException
	 */
	public function count($column = null){
		if($column === null){
			$this->performed || $this->run();
			return (int) $this->connection->getDriver()->getNumRows($this->resultSet);
		}
		return $this->aggregation("COUNT(:$column)");
	}


	/**
	 * Executes aggregation function.
	 * @param string $function
	 * @return mixed
	 */
	public function aggregation($function){
		$clone = clone $this;
		$clone->columns = (array) $function;
		return $clone->fetchSingle();
	}


	/**
	 * Returns the sum of column values.
	 * @param string $column
	 * @return mixed
	 */
	public function sum($column){
		return $this->aggregation("SUM($column)");
	}


	/**
	 * Returns the minimum value of column.
	 * @param string $column
	 * @return mixed
	 */
	public function min($column){
		return $this->aggregation("MIN($column)");
	}


	/**
	 * Returns the maximum value of column.
	 * @param string $column
	 * @return mixed
	 */
	public function max($column){
		return $this->aggregation("MAX($column)");
	}


	/**
	 * Explains performed query.
	 * @return array
	 */
	public function explain(){
		$driver = $this->getConnection()->getDriver();
		$query = $driver->runQuery("EXPLAIN $this");

		$rows = array();
		while($row = $driver->fetch($query)){
			$rows[] = $row;
		}

		return $rows;
	}


	/**
	 * Sets column type.
	 * @param string $column
	 * @param string $type
	 * @return Result fluent interface
	 */
	public function setType($column, $type){
		$this->columnTypes[$column] = $type;
		return $this;
	}


	/**
	 * Sets multiple column types at once.
	 * @param array|Traversable $types
	 * @return Result fluent interface
	 */
	public function setTypes($types){
		if(!($types instanceof Traversable || is_array($types)))
			throw new InvalidArgumentException('Types must be an array or Traversable.');
		foreach($types as $column => $type){
			$this->setType($column, $type);
		}
		return $this;
	}


	/**
	 * Detects column types.
	 * @return Result fluent interface
	 */
	public function detectTypes(){
		$table = $this->getTable();
		$this->performed || $this->run();

		// Try fetch from cache
		$types = (array) $this->connection->getCache()->fetch($table . '_detectedTypes');

		if(empty($types)){
			try{
				$types = $this->connection->getDriver()->getColumnTypes($this->resultSet, $table);
			} catch(NeevoException $e){
				return $this;
			}
		}

		foreach((array) $types as $col => $type){
			$this->columnTypes[$col] = $this->resolveType($type);
		}

		$this->connection->getCache()->store($table . '_detectedTypes', $this->columnTypes);
		return $this;
	}


	/**
	 * Resolves vendor column type.
	 * @param string $type
	 * @return string
	 */
	protected function resolveType($type){
		static $patterns = array(
			'bool|bit' => Manager::BOOL,
			'bin|blob|bytea' => Manager::BINARY,
			'string|char|text|bigint|longlong' => Manager::TEXT,
			'int|long|byte|serial|counter' => Manager::INT,
			'float|real|double|numeric|number|decimal|money|currency' => Manager::FLOAT,
			'time|date|year' => Manager::DATETIME
		);

		foreach($patterns as $vendor => $universal){
			if(preg_match("~$vendor~i", $type))
				return $universal;
		}
		return Manager::TEXT;
	}


	/**
	 * Converts value to a specified type.
	 * @param mixed $value
	 * @param string $type
	 * @return mixed
	 */
	protected function convertType($value, $type){
		$dateFormat = $this->connection['result']['formatDate'];
		if($value === null)
			return null;
		switch($type){
			case Manager::TEXT:
				return (string) $value;

			case Manager::INT:
				return (int) $value;

			case Manager::FLOAT:
				return (float) $value;

			case Manager::BOOL:
				return ((bool) $value) && $value !== 'f' && $value !== 'F';

			case Manager::BINARY:
				return $this->connection->getDriver()->unescape($value, $type);

			case Manager::DATETIME:
				if((int) $value === 0)
					return null;
				elseif(!$dateFormat)
					return new DateTime(is_numeric($value) ? date('Y-m-d H:i:s', $value) : $value);
				elseif($dateFormat == 'U')
					return is_numeric($value) ? (int) $value : strtotime($value);
				elseif(is_numeric($value))
					return date($dateFormat, $value);
				else{
					$d = new DateTime($value);
					return $d->format($dateFormat);
				}

			default:
				return $value;
		}
	}


	/**
	 * Sets table alias to be used when in subquery.
	 * @param string $alias
	 * @return Result fluent interface
	 */
	public function setAlias($alias){
		$this->tableAlias = $alias;
		return $this;
	}


	/**
	 * Returns table alias used in subquery.
	 * @return string|null
	 */
	public function getAlias(){
		return $this->tableAlias ? $this->tableAlias : null;
	}


	/**
	 * Sets class to use as a row class.
	 * @param string $className
	 * @return Result fluent interface
	 * @throws NeevoException
	 */
	public function setRowClass($className){
		if(!class_exists($className))
			throw new NeevoException("Cannot set row class '$className'.");
		$this->rowClass = $className;
		return $this;
	}


	/**
	 * Returns the result iterator.
	 * @return ResultIterator
	 */
	public function getIterator(){
		if(!isset($this->iterator))
			$this->iterator = new ResultIterator($this);
		return $this->iterator;
	}


	public function getGrouping(){
		return $this->grouping;
	}


	public function getColumns(){
		return $this->columns;
	}


	public function getJoins(){
		if(!empty($this->joins))
			return $this->joins;
		return array();
	}


	/**
	 * Returns full table name (with prefix) if available.
	 * @return string|null
	 */
	public function getTable(){
		if($this->source instanceof self)
			return null;
		return parent::getTable();
	}


	/**
	 * Returns the source for the statement.
	 * @return string|Result
	 */
	public function getSource(){
		return $this->source;
	}


}
