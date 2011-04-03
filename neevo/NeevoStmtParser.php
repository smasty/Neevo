<?php
/**
 * Neevo - Tiny open-source database abstraction layer for PHP
 *
 * Copyright 2010-2011 Martin Srank (http://smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * @author	 Martin Srank (http://smasty.net)
 * @license	http://neevo.smasty.net/license MIT license
 * @link    	 http://neevo.smasty.net/
 *
 */


/**
 * NeevoStmt to SQL command parser.
 * @author Martin Srank
 * @package Neevo
 */
class NeevoStmtParser {


	/** @var NeevoStmtBase */
	protected $stmt;

	/** @var array */
	protected $clauses = array();


	/*	 ************  Parsing  ************  */


	/**
	 * Parse the instance.
	 * @param NeevoStmtBase $statement
	 * @return string The SQL statement
	 */
	public function parse(NeevoStmtBase $statement){
		$this->stmt = $statement;
		$where = $order = $group = $limit = $q = '';
		$table = $this->escapeValue($statement->getTable(), Neevo::IDENTIFIER);

		if($this->stmt instanceof NeevoResult && $this->stmt->getJoins()){
			$table = $table . $this->parseJoin();
		}
		if($this->stmt->getConditions()){
			$where = $this->parseWhere();
		}
		if($this->stmt instanceof NeevoResult && $this->stmt->getGrouping()){
			$group = $this->parseGrouping();
		}
		if($this->stmt->getOrdering()){
			$order = $this->parseOrdering();
		}

		$this->clauses = array($table, $where, $group, $order);

		if($this->stmt->getType() == Neevo::STMT_SELECT){
			$q = $this->parseSelectStmt();
		} elseif($statement->getType() == Neevo::STMT_INSERT && $statement->getValues()){
			$q = $this->parseInsertStmt();
		} elseif($statement->getType() == Neevo::STMT_UPDATE && $statement->getValues()){
			$q = $this->parseUpdateStmt();
		} elseif($statement->getType() == Neevo::STMT_DELETE){
			$q = $this->parseDeleteStmt();
		}

		$this->stmt = null;
		$this->clauses = array();

		return $q . ';';
	}


	/**
	 * Parse SELECT statement.
	 * @return string
	 */
	protected function parseSelectStmt(){
		$cols = $this->stmt->getColumns();
		list($table, $where, $group, $order) = $this->clauses;
		foreach($cols as $key => $col){
			$cols[$key] = $this->tryDelimite($col);
		}
		$cols = implode(', ', $cols);

		return $this->applyLimit("SELECT $cols FROM " . $table . $where . $group . $order);
	}


	/**
	 * Parse INSERT statement.
	 * @return string
	 */
	protected function parseInsertStmt(){
		$cols = array();
		foreach($this->escapeValue($this->stmt->getValues()) as $col => $value){
			$cols[] = $this->parseFieldName($col);
			$values[] = $value;
		}
		$data = ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $values). ')';

		return 'INSERT INTO ' . $this->clauses[0] . $data;
	}


	/**
	 * Parse UPDATE statement.
	 * @return string
	 */
	protected function parseUpdateStmt(){
		$values = array();
		list($table, $where) = $this->clauses;
		foreach($this->escapeValue($this->stmt->getValues()) as $col => $value){
			$values[] = $this->parseFieldName($col) . ' = ' . $value;
		}
		$data = ' SET ' . implode(', ', $values);

		return 'UPDATE ' . $table . $data . $where;
	}


	/**
	 * Parse DELETE statement.
	 * @return string
	 */
	protected function parseDeleteStmt(){
		list($table, $where) = $this->clauses;

		return 'DELETE FROM ' . $table . $where;
	}


	/**
	 * Parse JOIN clause.
	 * @throws NeevoException
	 * @return string
	 */
	protected function parseJoin(){
		$result = '';
		foreach($this->stmt->getJoins() as $join){
			list($table, $cond, $type) = $join;
			$type = strtoupper(substr($type, 5));
			$type .= ($type === '') ? '' : ' ';
			$result .= "\n{$type}JOIN $table ON $cond";
		}
		return $this->tryDelimite($result);
	}


	/**
	 * Parse WHERE clause.
	 * @return string
	 */
	protected function parseWhere(){
		$conds = $this->stmt->getConditions();

		// Unset glue on last condition
		unset($conds[count($conds)-1]['glue']);

		$conditions = array();
		foreach($conds as $cond){

			// Conditions with modifiers
			if($cond['simple'] === false){
				$values = $this->escapeValue($cond['values'], $cond['types']);
				$s = '(' . $this->applyModifiers($cond['expr'], $cond['modifiers'], $values) . ')';
				if(isset($cond['glue'])){
					$s .= ' ' . $cond['glue'];
				}

				$conditions[] = $s;
				continue;
			}

			// Simple conditions
			$field = $this->parseFieldName($cond['field']);
			$operator = '';
			$value = $cond['value'];
			if($value === null){ // field IS NULL
				$value = ' IS NULL';
			} elseif($value === true){	// field
				$value = '';
			} elseif($value === false){ // NOT field
				$value = $field;
				$field = 'NOT ';
			} elseif(is_array($value)){ // field IN (array)
				$value = ' IN ' . $this->escapeValue($value, Neevo::ARR);
			} elseif($value instanceof NeevoLiteral){ // field = SQL literal
				$operator = ' = ';
				$value = $this->escapeValue($value, Neevo::LITERAL);
			} elseif($value instanceof DateTime){ // field = DateTime
				$operator = ' = ';
				$value = $this->escapeValue($value, Neevo::DATETIME);
			} else{ // field = value
				$operator = ' = ';
				$value = $this->escapeValue($value);
			}
			$s = '(' . $field . $operator . $value . ')';
			if(isset($cond['glue'])){
				$s .= ' '.$cond['glue'];
			}

			$conditions[] = $s;

		}

		return ' WHERE ' . implode(' ', $conditions);
	}


	/**
	 * Parse ORDER BY clause.
	 * @return string
	 */
	protected function parseOrdering(){
		$order = array();
		foreach($this->stmt->getOrdering() as $rule){
			list($field, $type) = $rule;
			$order[] = $this->tryDelimite($field) . ($type !== null ? ' ' . $type : '');
		}
		return ' ORDER BY ' . implode(', ', $order);
	}


	/**
	 * Parse GROUP BY clause.
	 * @return string
	 */
	protected function parseGrouping(){
		list($group, $having) = $this->stmt->getGrouping();
		return $this->tryDelimite(" GROUP BY $group" . ($having !== null ? " HAVING $having" : ""));
	}


	/**
	 * Parse column name.
	 * @param string|array|NeevoLiteral $field
	 * @return string
	 */
	protected function parseFieldName($field){
		// preg_replace callback behaviour
		if(is_array($field)){
			$field = $field[0];
		}
		if($field instanceof NeevoLiteral){
			return $field->value;
		}

		$field = trim($field);

		if($field == '*'){
			return $field;
		}

		if(strpos($field, ' ')){
			return $field;
		}

		$field = str_replace(':', '', $field);

		if(strpos($field, '.') !== false){
			$prefix = $this->stmt->getConnection()->getPrefix();
			$field = $prefix . $field;
		}

		return $this->stmt->getDriver()->escape($field, Neevo::IDENTIFIER);
	}


	/**
	 * Apply LIMIT/OFFSET to SQL command.
	 * @param string $sql SQL command
	 * @return string
	 */
	protected function applyLimit($sql){
		list($limit, $offset) = $this->stmt->getLimit();

		if((int) $limit > 0){
			$sql .= ' LIMIT ' . (int) $limit;
			if((int) $offset > 0){
				$sql .= ' OFFSET ' . (int) $offset;
			}
		}
		return $sql;
	}


	/*	 ************  Escaping, formatting, quoting  ************  */


	/**
	 * Escape given value.
	 * @param mixed|array $value
	 * @param string|array|null $type
	 * @return mixed|array
	 */
	protected function escapeValue($value, $type = null){
		if(!$type){
			// NULL type
			if($value === null)
				return 'NULL';

			// Multiple values w/o types
			elseif(is_array($value)){
				foreach($value as $k => $v)
					$value[$k] = $this->escapeValue($v);
				return $value;
			}

			// Value w/o type
			else{
				if($value instanceof DateTime){
					return $this->escapeValue($value, Neevo::DATETIME);
				} elseif($value instanceof NeevoLiteral){
					return $value->value;
				} else{
					return is_numeric($value)
						? 1 * $value : $this->stmt->getDriver()->escape($value, Neevo::TEXT);
				}
			}
		}

		// Multiple values w/ types
		elseif(is_array($type)){
			foreach($value as $k => $v)
				$value[$k] = $this->escapeValue($v, $type[$k]);
			return $value;
		}

		// Single value vith type
		elseif($type !== null){
			if($type === Neevo::INT){
				return (int) $value;
			} elseif($type === Neevo::FLOAT){
				return (float) $value;
			} elseif($type === Neevo::ARR){
				$arr = ($value instanceof Traversable) ? iterator_to_array($value) : (array) $value;
				return '(' . implode(', ', $this->escapeValue($value)) . ')';
			} elseif($type === Neevo::LITERAL){
				return ($value instanceof NeevoLiteral) ? $value->value : $value;
			} else{
				return $this->stmt->getDriver()->escape($value, $type);
			}
		}
	}


	/**
	 * Apply modifiers to expression.
	 * @param string $expr
	 * @param array $modifiers
	 * @param array $values
	 * @return string
	 */
	protected function applyModifiers($expr, array $modifiers, array $values){
		foreach($modifiers as &$mod){
			$mod = "/$mod/";
		}
		$expr = $this->tryDelimite($expr);
		return preg_replace($modifiers, $values, $expr, 1);
	}


	/**
	 * Try deimite fields in given expression.
	 * @param NeevoLiteral $expr
	 * @return string
	 */
	protected function tryDelimite($expr){
		if($expr instanceof NeevoLiteral){
			return $expr->value;
		}
		return preg_replace_callback('~:([a-z_\*][a-z0-9._\*]*)~', array($this, 'parseFieldName'), $expr);
	}


}
