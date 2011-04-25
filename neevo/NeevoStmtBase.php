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
 * Neevo statement abstract base ancestor.
 *
 * @method NeevoStmtBase and($expr, $value = true)
 * @method NeevoStmtBase or($expr, $value = true)
 * @method NeevoStmtBase if($condition)
 * @method NeevoStmtBase else()
 * @method NeevoStmtBase end()
 *
 * @author Martin Srank
 * @package Neevo
 */
abstract class NeevoStmtBase {


	/** @var string */
	protected $source;

	/** @var string */
	protected $type;

	/** @var int */
	protected $limit;

	/** @var int */
	protected $offset;

	/** @var array */
	protected $conds = array();

	/** @var array */
	protected $sorting = array();

	/** @var float */
	protected $time;

	/** @var bool */
	protected $performed;

	/** @var NeevoConnection */
	protected $connection;

	/** @var array Event type conversion table */
	protected static $eventTable = array(
		Neevo::STMT_SELECT => INeevoObserver::SELECT,
		Neevo::STMT_INSERT => INeevoObserver::INSERT,
		Neevo::STMT_UPDATE => INeevoObserver::UPDATE,
		Neevo::STMT_DELETE => INeevoObserver::DELETE
	);

	/** @var array */
	private $_stmtConds = array();


	/**
	 * Create statement.
	 * @param NeevoConnection $connection
	 * @return void
	 */
	public function __construct(NeevoConnection $connection){
		$this->connection = $connection;
	}


	/**
	 * String representation of object.
	 * @return string
	 */
	public function __toString(){
		return (string) $this->parse();
	}


	/**
	 * Create clone of object.
	 * @return void
	 */
	public function __clone(){
		$this->resetState();
	}


	/**
	 * @return NeevoStmtBase fluent interface
	 * @internal
	 * @throws BadMethodCallException
	 * @throws InvalidArgumentException
	 */
	public function __call($name, $args){
		$name = strtolower($name);

		// AND/OR where() glues
		if(in_array($name, array('and', 'or'))){
			if($this->_validateConditions()){
				return $this;
			}
			$this->resetState();
			$this->conds[count($this->conds)-1]['glue'] = strtoupper($name);
			if(count($args) >= 1){
				call_user_func_array(array($this, 'where'), $args);
			}
			return $this;
		}

		// Conditional statements
		elseif(in_array($name, array('if', 'else', 'end'))){

			// Parameter counts
			if(count($args) < 1 && $name == 'if'){
				throw new InvalidArgumentException('Missing argument 1 for '.__CLASS__."::$name().");
			}

			$conds = & $this->_stmtConds;
			if($name == 'if'){
				$conds[] = (bool) $args[0];
			} elseif($name == 'else'){
				$conds[ count($conds)-1 ] = !end($conds);
			} elseif($name == 'end'){
				array_pop($conds);
			}

			return $this;

		}
		throw new BadMethodCallException('Call to undefined method '.__CLASS__."::$name()");
	}


	/*  ************  Statement clauses  ************  */


	/**
	 * Set WHERE condition. Accepts infinite arguments.
	 *
	 * More calls append conditions with 'AND' operator. Conditions can also be specified
	 * by calling and() / or() methods the same way as where().
	 * Corresponding operator will be used.
	 *
	 * Usage is similar to printf(). Available modifiers are:
	 * - %b - boolean
	 * - %i - integer
	 * - %f - float
	 * - %s - string
	 * - %bin - binary data
	 * - %d - date, time
	 * - %a - array
	 * - %l - SQL lieral
	 * - %id - SQL identifier
	 * - %sub - subquery
	 *
	 * In simple mode, argument is SQL identifier, second is value:
	 * true, false, scalar, null, array, NeevoLiteral or NeevoResult.
	 *
	 * @param string $expr
	 * @param mixed $value
	 * @return NeevoStmtBase fluent interface
	 */
	public function where($expr, $value = true){
		if(is_array($expr) && $value === true){
			return call_user_func_array(array($this, 'where'), $expr);
		}

		if($this->_validateConditions()){
			return $this;
		}
		$this->resetState();

		// Simple format
		if(strpos($expr, '%') === false){
			$field = trim($expr);
			$this->conds[] = array(
				'simple' => true,
				'field' => $field,
				'value' => $value,
				'glue' => 'AND'
			);
			return $this;
		}

		// Format with modifiers
		$args = func_get_args();
		array_shift($args);
		preg_match_all('~%(bin|sub|b|i|f|s|d|a|l)?~i', $expr, $matches);
		$this->conds[] = array(
			'simple' => false,
			'expr' => $expr,
			'modifiers' => $matches[0],
			'types' => $matches[1],
			'values' => $args,
			'glue' => 'AND'
		);
		return $this;
	}


	/**
	 * Define order. More calls append rules.
	 * @param string|array $rule
	 * @param string $order Use constants - Neevo::ASC, Neevo::DESC
	 * @return NeevoStmtBase fluent interface
	 */
	public function order($rule, $order = null){
		if($this->_validateConditions()){
			return $this;
		}
		$this->resetState();

		if(is_array($rule)){
			foreach($rule as $key => $val){
				$this->order($key, $val);
			}
			return $this;
		}
		$this->sorting[] = array($rule, $order);

		return $this;
	}


	/**
	 * Set LIMIT and OFFSET clauses.
	 * @param int $limit
	 * @param int $offset
	 * @return NeevoStmtBase fluent interface
	 */
	public function limit($limit, $offset = null){
		if($this->_validateConditions()){
			return $this;
		}
		$this->resetState();
		$this->limit = array($limit, ($offset !== null && $this->type === Neevo::STMT_SELECT) ? $offset : null);
		return $this;
	}


	/**
	 * Randomize order. Removes any other order clause.
	 * @return NeevoStmtBase fluent interface
	 */
	public function rand(){
		if($this->_validateConditions()){
			return $this;
		}
		$this->resetState();
		$this->connection->getDriver()->rand($this);
		return $this;
	}


	/*  ************  Statement manipulation  ************  */


	/**
	 * Print out syntax highlighted statement.
	 * @param bool $return
	 * @return string|NeevoStmtBase fluent interface
	 */
	public function dump($return = false){
		$sql = (PHP_SAPI === 'cli') ? $this->parse() : Neevo::highlightSql($this->parse());
		if(!$return){
			echo $sql;
		}
		return $return ? $sql : $this;
	}


	/**
	 * Perform the statement.
	 * @return resource|bool
	 */
	public function run(){
		$start = -microtime(true);

		$query = $this->performed ?
			$this->resultSet : $this->connection->getDriver()->query($this->parse());

		$this->time = $start + microtime(true);

		$this->performed = true;
		$this->resultSet = $query;

		$this->connection->notifyObservers(self::$eventTable[$this->type], $this);

		return $query;
	}


	/**
	 * Perform the statement. Alias for run().
	 * @return resource|bool
	 */
	public function exec(){
		return $this->run();
	}


	/**
	 * Build the SQL statement from the instance.
	 * @return string The SQL statement
	 * @internal
	 */
	public function parse(){
		$this->connection->connect();

		$parser = $this->connection->getParser();
		$instance = new $parser($this);
		return $instance->parse();
	}


	/*  ************  Getters  ************  */


	/**
	 * Query execution time.
	 * @return int
	 */
	public function time(){
		return $this->time;
	}


	/**
	 * If query was performed.
	 * @return bool
	 */
	public function isPerformed(){
		return $this->performed;
	}


	/**
	 * Get full table name (with prefix).
	 * @return string
	 */
	public function getTable(){
		$table = str_replace(':', '', $this->source);
		$prefix = $this->connection->getPrefix();
		return $prefix . $table;
	}


	/**
	 * Statement type.
	 * @return string
	 */
	public function getType(){
		return $this->type;
	}


	/**
	 * Get LIMIT and OFFSET clauses.
	 * @return int
	 */
	public function getLimit(){
		return $this->limit;
	}


	/**
	 * Statement WHERE clause.
	 * @return array
	 */
	public function getConditions(){
		return $this->conds;
	}


	/**
	 * Statement ORDER BY clause.
	 * @return array
	 */
	public function getSorting(){
		return $this->sorting;
	}


	/**
	 * Name of the PRIMARY KEY column.
	 * @return string|null
	 */
	public function getPrimaryKey(){
		$table = $this->getTable();
		if($table === null){
			return null;
		}
		$key = null;
		$cached = $this->connection->getCache()->fetch($table.'_primaryKey');

		if($cached === null){
			try{
				$key = $this->connection->getDriver()->getPrimaryKey($table);
			} catch(NeevoException $e){
				return null;
			}
			$this->connection->getCache()->store($table.'_primaryKey', $key);
			return $key === '' ? null : $key;
		}
		return $cached === '' ? null : $cached;
	}


	public function getForeignKey($table){
		$primary = $this->getPrimaryKey();
		return $table . '_' . ($primary !== null ? $primary : 'id' );
	}


	/*  ************  Internal methods  ************  */


	/**
	 * Get the connection instance.
	 * @return NeevoConnection
	 */
	public function getConnection(){
		return $this->connection;
	}


	/**
	 * Reset the state of the statement.
	 * @return void
	 */
	public function resetState(){
		$this->performed = false;
		$this->time = null;
	}


	/**
	 * @deprecated
	 * @internal
	 */
	public function orderBy(){
		trigger_error(__METHOD__ . ' is deprecated, use ' . __CLASS__ . '::order() instead.', E_USER_WARNING);
		return call_user_func_array(array($this, 'order'), func_get_args());
	}


	/**
	 * Validate the current statement condition.
	 * @return bool
	 */
	protected function _validateConditions(){
		if(empty($this->_stmtConds)){
			return false;
		}
		foreach($this->_stmtConds as $cond){
			if($cond) continue;
			else return true;
		}
	}


}
