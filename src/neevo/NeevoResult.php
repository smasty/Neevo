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
 * Represents a result. Can be iterated, counted and provides fluent interface.
 *
 * @method NeevoResult as($alias)
 *
 * @author Martin Srank
 * @package Neevo
 */
class NeevoResult extends NeevoStmtBase implements IteratorAggregate, Countable {


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

	/** @var string */
	private $rowClass = 'NeevoRow';

	/** @var array */
	private $columnTypes = array();

	/** @var bool */
	private $detectTypes;

	/** @var array */
	private $referencedTables;


	/**
	 * Create SELECT statement.
	 * @param NeevoConnection $connection
	 * @param string|array $columns
	 * @param string|NeevoResult $source Table name or subquery
	 * @return void
	 * @throws InvalidArgumentException
	 */
	public function __construct(NeevoConnection $connection, $columns = null, $source = null){
		parent::__construct($connection);

		if($columns === null && $source === null){
			throw new InvalidArgumentException('Select table missing.');
		}
		if($source === null){
			$columns = '*';
			$source = func_get_arg(1);
		}

		if(!is_string($source) && !($source instanceof self)){
			throw new InvalidArgumentException('Source must be a string or instance of NeevoResult, ' . gettype($source) . ' given.');
		}

		$this->resetState();
		$this->type = Neevo::STMT_SELECT;
		$this->columns = is_string($columns) ? explode(',', $columns) : $columns;

		if($columns === array()){
			throw new InvalidArgumentException('No columns given.');
		}

		$this->source = $source;
		$this->detectTypes = (bool) $connection['detectTypes'];

		// Experimental feature - may be buggy!
		if($connection['autoJoin'] === true){
			$referenced = array();
			foreach($this->columns as $col){
				if(strpos($col, '.') !== false){
					$referenced[] = trim(str_replace(':', '', substr($col, 0, strpos($col, '.'))));
				}
			}
			foreach(array_unique($referenced) as $ref){
				$this->leftJoin(":$ref", ":$ref." . $this->getForeignKey($this->getTable()) . " = :" . $this->getTable() . "." . $this->getPrimaryKey());
			}
		}

		$this->setRowClass($connection['rowClass']);
	}


	/**
	 * Destroy the result set resource and free memory.
	 * @return void
	 */
	public function __destruct(){
		try{
			$this->connection->getDriver()->free($this->resultSet);
		} catch(NeevoImplemenationException $e){}

		$this->resultSet = null;
	}


	public function __call($name, $args){
		$name = strtolower($name);

		if($name === 'as'){
			return $this->setAlias(isset($args[0]) ? $args[0] : null);
		}

		return parent::__call($name, $args);
	}


	/*  ************  Statement clauses  ************  */


	/**
	 * Define grouping rule.
	 * @param string $rule
	 * @param string $having Optional
	 * @return NeevoResult fluent interface
	 */
	public function group($rule, $having = null){
		if($this->_validateConditions()){
			return $this;
		}
		$this->resetState();
		$this->grouping = array($rule, $having);
		return $this;
	}


	/**
	 * Perform JOIN on tables.
	 * @param string|NeevoResult $source Table name or subquery
	 * @param string $condition
	 * @return NeevoResult fluent interface
	 */
	public function join($source, $condition){
		if($this->_validateConditions()){
			return $this;
		}
		if(!is_string($source) && !($source instanceof self)){
			throw new InvalidArgumentException('Source must be a string or instance of NeevoResult, ' . gettype($source) . ' given.');
		}
		$this->resetState();
		$type = (func_num_args() > 2) ? func_get_arg(2) : '';

		$this->joins[] = array($source, $condition, $type);

		return $this;
	}


	/**
	 * Perform LEFT JOIN on tables.
	 * @param string|NeevoResult $source Table name or subquery
	 * @param string $condition
	 * @return NeevoResult fluent interface
	 */
	public function leftJoin($source, $condition){
		return $this->join($source, $condition, Neevo::JOIN_LEFT);
	}


	/**
	 * Perform INNER JOIN on tables.
	 * @param string|NeevoResult $source Table name or subquery
	 * @param string $condition
	 * @return NeevoResult fluent interface
	 */
	public function innerJoin($source, $condition){
		return $this->join($source, $condition, Neevo::JOIN_INNER);
	}


	/*  ************  Result manipulation  ************  */


	/**
	 * Fetch the row on current position.
	 * @return NeevoRow|FALSE
	 */
	public function fetch(){
		$this->performed || $this->run();

		$row = $this->connection->getDriver()->fetch($this->resultSet);
		if(!is_array($row)){
			return false;
		}

		// Type converting
		if($this->detectTypes){
			$this->detectTypes();
		}
		if(!empty($this->columnTypes)){
			foreach($this->columnTypes as $col => $type){
				if(isset($row[$col])){
					$row[$col] = $this->convertType($row[$col], $type);
				}
			}
		}
		return new $this->rowClass($row, $this);
	}


	/**
	 * Fetch all rows in result set.
	 * @param int $limit Limit number of returned rows
	 * @param int $offset Seek to offset (fails on unbuffered results)
	 * @return array
	 */
	public function fetchAll($limit = null, $offset = null){
		$limit = $limit === null ? -1 : (int) $limit;
		if($offset !== null){
			$this->seek((int) $offset);
		}

		$row = $this->fetch();
		if(!$row){
			return array();
		}

		$rows = array();
		do{
			if($limit === 0){
				break;
			}
			$rows[] = $row;
			$limit--;
		} while($row = $this->fetch());

		return $rows;
	}


	/**
	 * Fetch the first value from current row.
	 * @return mixed
	 */
	public function fetchSingle(){
		$this->performed || $this->run();
		$row = $this->connection->getDriver()->fetch($this->resultSet);

		if(!$row){
			return false;
		}
		$value = reset($row);

		// Type converting
		if($this->detectTypes){
			$this->detectTypes();
		}
		if(!empty($this->columnTypes)){
			$key = key($row);
			if(isset($this->columnTypes[$key])){
				$value = $this->convertType($value, $this->columnTypes[$key]);
			}
		}

		return $value;
	}


	/**
	 * Fetch rows as $key=>$value pairs.
	 * @param string $key Key column
	 * @param string $value Value column. NULL for all specified columns.
	 * @return array
	 */
	public function fetchPairs($key, $value = null){
		$clone = clone $this;

		// If executed w/o needed cols, force exec w/ them.
		if(!in_array('*', $clone->columns)){
			if(!in_array($key, $clone->columns)){
				$clone->columns[] = $key;
			}
			if($value !== null && !in_array($value, $clone->columns)){
				$clone->columns[] = $value;
			}
		}

		$rows = array();
		while($row = $clone->fetch()){
			if(!$row){
				return array();
			}
			$rows[$row[$key]] = $value === null ? $row : $row->$value;
		}

		return $rows;
	}


	/**
	 * Move internal result pointer.
	 * @param int $offset
	 * @return bool
	 * @throws NeevoException
	 */
	public function seek($offset){
		$this->performed || $this->run();
		$seek = $this->connection->getDriver()->seek($this->resultSet, $offset);
		if($seek){
			return $seek;
		}
		throw new NeevoException("Cannot seek to offset $offset.");
	}


	public function rows(){
		return $this->count();
	}


	/*  ************  Aggregation  ************  */


	/**
	 * Count number of rows.
	 * @param string $column
	 * @return int
	 * @throws NeevoDriverException
	 */
	public function count($column = null){
		if($column === null){
			$this->performed || $this->run();
			return (int) $this->connection->getDriver()->rows($this->resultSet);
		}
		return $this->aggregation("COUNT(:$column)");
	}


	/**
	 * Execute aggregation function.
	 * @param string $function
	 * @return mixed
	 */
	public function aggregation($function){
		$clone = clone $this;
		$clone->columns = (array) $function;
		return $clone->fetchSingle();
	}


	/**
	 * Get the sum of column a values.
	 * @param string $column
	 * @return mixed
	 */
	public function sum($column){
		return $this->aggregation("SUM(:$column)");
	}


	/**
	 * Get the minimum value of column.
	 * @param string $column
	 * @return mixed
	 */
	public function min($column){
		return $this->aggregation("MIN(:$column)");
	}


	/**
	 * Get the maximum value of column.
	 * @param string $column
	 * @return mixed
	 */
	public function max($column){
		return $this->aggregation("MAX(:$column)");
	}


	/*  ************  Column type detection  ************  */


	/**
	 * Set column type.
	 * @param string $column
	 * @param string $type
	 * @return NeevoResult fluent interface
	 */
	public function setType($column, $type){
		$this->columnTypes[$column] = $type;
		return $this;
	}


	/**
	 * Set multiple column types at once.
	 * @param array $types
	 * @return NeevoResult fluent interface
	 */
	public function setTypes(array $types){
		foreach($types as $column => $type){
			$this->setType($column, $type);
		}
		return $this;
	}


	/**
	 * Detect column types.
	 * @return NeevoResult fluent interface
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
	 * Resolve vendor column type.
	 * @param string $type
	 * @return string
	 */
	private function resolveType($type){
		static $patterns = array(
			'bool|bit' => Neevo::BOOL,
			'bin|blob|bytea' => Neevo::BINARY,
			'string|char|text|bigint|longlong' => Neevo::TEXT,
			'int|long|byte|serial|counter' => Neevo::INT,
			'float|real|double|numeric|number|decimal|money|currency' => Neevo::FLOAT,
			'time|date|year' => Neevo::DATETIME
		);

		foreach($patterns as $vendor => $universal){
			if(preg_match("~$vendor~i", $type)){
				return $universal;
			}
		}
		return Neevo::TEXT;
	}


	/**
	 * Convert value to a specified type.
	 * @param mixed $value
	 * @param string $type
	 * @return mixed
	 */
	private function convertType($value, $type){
		$dateFormat = $this->connection['formatDateTime'];
		if($value === null){
			return null;
		}
		switch($type){
			case Neevo::TEXT:
				return (string) $value;

			case Neevo::INT:
				return (int) $value;

			case Neevo::FLOAT:
				return (float) $value;

			case Neevo::BOOL:
				return ((bool) $value) && $value !== 'f' && $value !== 'F';

			case Neevo::BINARY:
				return $this->connection->getDriver()->unescape($value, $type);

			case Neevo::DATETIME:
				if((int) $value === 0){
					return null;
				} elseif(!$dateFormat){
					return new DateTime(is_numeric($value) ? date('Y-m-d H:i:s', $value) : $value);
				} elseif($dateFormat == 'U'){
					return is_numeric($value) ? (int) $value : strtotime($value);
				} elseif(is_numeric($value)){
					return date($dateFormat, $value);
				} else{
					$d = new DateTime($value);
					return $d->format($dateFormat);
				}

			default:
				return $value;
		}
	}


	/*  ************  Getters & setters  ************  */


	/**
	 * Set table alias to be used when in subquery.
	 * @param string $alias
	 * @return NeevoResult fluent interface
	 */
	public function setAlias($alias){
		$this->tableAlias = $alias;
		return $this;
	}


	/**
	 * Get table alias used in subquery.
	 * @return string|null
	 */
	public function getAlias(){
		return $this->tableAlias ? $this->tableAlias : null;
	}


	/**
	 * Set class to use as a row class.
	 * @param string $className
	 * @return NeevoResult fluent interface
	 * @throws NeevoException
	 */
	public function setRowClass($className){
		if(!class_exists($className)){
			throw new NeevoException("Cannot set row class '$className'.");
		}
		$this->rowClass = $className;
		return $this;
	}


	/**
	 * Get referenced row from given table.
	 * @param string $table
	 * @param NeevoResult $row
	 * @param string $foreign
	 * @return NeevoRow|null
	 */
	public function getReferencedRow($table, & $row, $foreign = null){
		$foreign = $foreign === null ? $this->getForeignKey($table) : $foreign;
		$rowID = $row->$foreign;
		$referenced = & $this->referencedTables[$table];

		if(empty($referenced)){
			$clone = clone $this;
			$clone->columns = array($foreign);
			$keys = $clone->fetchPairs($foreign, $foreign);
			$result = new NeevoResult($this->connection, '*', $table);
			$primary = $result->getPrimaryKey();
			$referenced = $result->where($primary, $keys)->fetchPairs($primary);
		}
		if(isset($referenced[$rowID])){
			return $referenced[$rowID];
		}
		return null;
	}


	/**
	 * Get the result iterator.
	 * @return NeevoResultIterator
	 */
	public function getIterator(){
		return new NeevoResultIterator($this);
	}


	public function getGrouping(){
		return $this->grouping;
	}


	public function getColumns(){
		return $this->columns;
	}


	public function getJoins(){
		if(!empty($this->joins)){
			return $this->joins;
		}
		return array();
	}


	/**
	 * Get full table name (with prefix) if available.
	 * @return string|null
	 */
	public function getTable(){
		if($this->source instanceof self){
			return null;
		}
		return parent::getTable();
	}


	/**
	 * Get the source for the statement.
	 * @return string|NeevoResult
	 */
	public function getSource(){
		return $this->source;
	}


	/*  ************  Internal methods  ************  */


	/**
	 * @deprecated
	 */
	public function groupBy(){
		trigger_error(__METHOD__ . ' is deprecated, use ' . __CLASS__ . '::group() instead.', E_USER_WARNING);
		return call_user_func_array(array($this, 'group'), func_get_args());
	}


	/**
	 * @deprecated
	 */
	public function fetchRow(){
		trigger_error(__METHOD__ . ' is deprecated, use ' . __CLASS__ . '::fetch() instead.', E_USER_WARNING);
		return $this->fetch();
	}


}
