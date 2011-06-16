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
 * NeevoStmt to SQL command parser.
 * @author Martin Srank
 * @package Neevo
 */
class NeevoParser {


	/** @var NeevoStmtBase */
	protected $stmt;

	/** @var array */
	protected $clauses = array();


	/**
	 * Instantiate the parser for given statement.
	 * @param NeevoStmtBase $statement
	 */
	public function __construct(NeevoStmtBase $statement){
		$this->stmt = $statement;
	}


	/*  ************  Parsing  ************  */


	/**
	 * Parse the given statement.
	 * @return string The SQL statement
	 */
	public function parse(){
		$where = $order = $group = $limit = $q = '';
		$source = $this->parseSource();

		if($this->stmt->getConditions()){
			$where = $this->parseWhere();
		}
		if($this->stmt instanceof NeevoResult && $this->stmt->getGrouping()){
			$group = $this->parseGrouping();
		}
		if($this->stmt->getSorting()){
			$order = $this->parseSorting();
		}

		$this->clauses = array($source, $where, $group, $order);

		if($this->stmt->getType() == Neevo::STMT_SELECT){
			$q = $this->parseSelectStmt();
		} elseif($this->stmt->getType() == Neevo::STMT_INSERT){
			$q = $this->parseInsertStmt();
		} elseif($this->stmt->getType() == Neevo::STMT_UPDATE){
			$q = $this->parseUpdateStmt();
		} elseif($this->stmt->getType() == Neevo::STMT_DELETE){
			$q = $this->parseDeleteStmt();
		}

		return $q;
	}


	/**
	 * Parse SELECT statement.
	 * @return string
	 */
	protected function parseSelectStmt(){
		$cols = $this->stmt->getColumns();
		list($source, $where, $group, $order) = $this->clauses;
		foreach($cols as $key => $col){
			$col = preg_match('~^[\w.]+$~', $col) ? ":$col" : $col;
			$cols[$key] = $this->tryDelimite($col);
		}
		$cols = implode(', ', $cols);

		return $this->applyLimit("SELECT $cols FROM " . $source . $where . $group . $order);
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
	 * Parse statement source.
	 * @return string
	 */
	protected function parseSource(){
		if(!($this->stmt instanceof NeevoResult)){
			return $this->escapeValue($this->stmt->getTable(), Neevo::IDENTIFIER);
		}
		if($this->stmt->getTable() !== null){
			$source = $this->escapeValue($this->stmt->getTable(), Neevo::IDENTIFIER);
		} else{
			$subq = $this->stmt->getSource();
			$alias = $this->escapeValue($subq->getAlias()
				? $subq->getAlias() : '_t', Neevo::IDENTIFIER);
			$source = "($subq) $alias";
		}

		foreach($this->stmt->getJoins() as $key => $join){
			list($join_source, $cond, $type) = $join;

			if($join_source instanceof NeevoResult){
				$join_alias = $this->escapeValue($join_source->getAlias()
					? $join_source->getAlias() : '_j' . ($key+1), Neevo::IDENTIFIER);

				$join_source = "($join_source) $join_alias";
			}
			$type = strtoupper(substr($type, 5));
			$type .= ($type === '') ? '' : ' ';
			$source .= " {$type}JOIN $join_source ON $cond";
		}
		return $this->tryDelimite($source);
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
			} elseif($value instanceof NeevoResult){
				$operator = ' IN ';
				$value = $this->escapeValue($value, Neevo::SUBQUERY);
			} elseif(is_array($value) || $value instanceof Traversable){ // field IN (array)
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
	protected function parseSorting(){
		$order = array();
		foreach($this->stmt->getSorting() as $rule){
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
		return $this->tryDelimite(" GROUP BY $group" . ($having !== null ? " HAVING $having" : ''));
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

		return $this->stmt->getConnection()->getDriver()->escape($field, Neevo::IDENTIFIER);
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


	/*  ************  Escaping, formatting, quoting  ************  */


	/**
	 * Escape given value.
	 * @param mixed|array|Traversable $value
	 * @param string|array|null $type
	 * @return mixed|array
	 */
	protected function escapeValue($value, $type = null){
		if(!$type){
			// NULL type
			if($value === null)
				return 'NULL';

			// Multiple values w/o types
			elseif(is_array($value) || $value instanceof Traversable){
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
						? 1 * $value
						: $this->stmt->getConnection()->getDriver()->escape($value, Neevo::TEXT);
				}
			}
		}

		// Multiple values w/ types
		elseif(is_array($type)){
			foreach($value as $k => $v)
				$value[$k] = $this->escapeValue($v, $type[$k]);
			return $value;
		}

		// Single value w/ type
		elseif($type !== null){
			if($type === Neevo::INT){
				return (int) $value;
			} elseif($type === Neevo::FLOAT){
				return (float) $value;
			} elseif($type === Neevo::SUBQUERY && $value instanceof NeevoResult){
				return "($value)";
			} elseif($type === Neevo::ARR){
				$arr = $value instanceof Traversable ? iterator_to_array($value) : (array) $value;
				return '(' . implode(', ', $this->escapeValue($arr)) . ')';
			} elseif($type === Neevo::LITERAL){
				return $value instanceof NeevoLiteral ? $value->value : $value;
			} else{
				return $this->stmt->getConnection()->getDriver()->escape($value, $type);
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
	 * Try delimite fields in given expression.
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
