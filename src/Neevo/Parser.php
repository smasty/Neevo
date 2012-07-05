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

use DateTime;
use Traversable;


/**
 * Neevo\BaseStatement to SQL command parser.
 * @author Smasty
 */
class Parser {


	/** @var BaseStatement */
	protected $stmt;

	/** @var array */
	protected $clauses = array();


	/**
	 * Instantiates the parser for given statement.
	 * @param BaseStatement $statement
	 */
	public function __construct(BaseStatement $statement){
		$this->stmt = $statement;
	}


	/**
	 * Parses the given statement.
	 * @return string The SQL statement
	 */
	public function parse(){
		$where = $order = $group = $limit = $q = '';
		$source = $this->parseSource();

		if($this->stmt->getConditions())
			$where = $this->parseWhere();
		if($this->stmt instanceof Result && $this->stmt->getGrouping())
			$group = $this->parseGrouping();
		if($this->stmt->getSorting())
			$order = $this->parseSorting();

		$this->clauses = array($source, $where, $group, $order);

		if($this->stmt->getType() === Manager::STMT_SELECT)
			$q = $this->parseSelectStmt();
		elseif($this->stmt->getType() === Manager::STMT_INSERT)
			$q = $this->parseInsertStmt();
		elseif($this->stmt->getType() === Manager::STMT_UPDATE)
			$q = $this->parseUpdateStmt();
		elseif($this->stmt->getType() === Manager::STMT_DELETE)
			$q = $this->parseDeleteStmt();

		return trim($q);
	}


	/**
	 * Parses SELECT statement.
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

		return $this->applyLimit("SELECT $cols\nFROM " . $source . $where . $group . $order);
	}


	/**
	 * Parses INSERT statement.
	 * @return string
	 */
	protected function parseInsertStmt(){
		$cols = array();
		foreach($this->escapeValue($this->stmt->getValues()) as $col => $value){
			$cols[] = $this->parseFieldName($col);
			$values[] = $value;
		}
		$data = ' (' . implode(', ', $cols) . ")\nVALUES (" . implode(', ', $values). ')';

		return 'INSERT INTO ' . $this->clauses[0] . $data;
	}


	/**
	 * Parses UPDATE statement.
	 * @return string
	 */
	protected function parseUpdateStmt(){
		$values = array();
		list($table, $where) = $this->clauses;
		foreach($this->escapeValue($this->stmt->getValues()) as $col => $value){
			$values[] = $this->parseFieldName($col) . ' = ' . $value;
		}
		$data = "\nSET " . implode(', ', $values);
		return 'UPDATE ' . $table . $data . $where;
	}


	/**
	 * Parses DELETE statement.
	 * @return string
	 */
	protected function parseDeleteStmt(){
		list($table, $where) = $this->clauses;
		return 'DELETE FROM ' . $table . $where;
	}


	/**
	 * Parses statement source.
	 * @return string
	 */
	protected function parseSource(){
		if(!$this->stmt instanceof Result)
			return $this->escapeValue($this->stmt->getTable(), Manager::IDENTIFIER);

		// Simple table
		if($this->stmt->getTable() !== null){
			$source = $this->escapeValue($this->stmt->getTable(), Manager::IDENTIFIER);
		}
		// Sub-select
		else{
			$subq = $this->stmt->getSource();
			$alias = $this->escapeValue($subq->getAlias()
				? $subq->getAlias() : '_table_', Manager::IDENTIFIER);
			$source = "(\n\t" . implode("\n\t", explode("\n", $subq)) . "\n) $alias";
		}
		$source = $this->tryDelimite($source);

		// JOINs
		foreach($this->stmt->getJoins() as $key => $join){
			list($join_source, $cond, $type) = $join;

			// JOIN sub-select
			if($join_source instanceof Result){
				$join_alias = $this->escapeValue($join_source->getAlias()
					? $join_source->getAlias() : '_join_' . ($key+1), Manager::IDENTIFIER);

				$join_source = "(\n\t" . implode("\n\t", explode("\n", $join_source)) . "\n) $join_alias";
			}
			// JOIN Literal
			elseif($join_source instanceof Literal){
				$join_source = $join_source->value;
			}
			// JOIN table
			elseif(is_scalar($join_source)){
				$join_source = $this->parseFieldName($join_source, true);
			}
			$type = strtoupper(substr($type, 5));
			$type .= ($type === '') ? '' : ' ';
				$source .= $cond instanceof Literal
					? "\n{$type}JOIN $join_source ON $cond->value"
					: $this->tryDelimite("\n{$type}JOIN $join_source ON $cond");
		}
		return $source;
	}


	/**
	 * Parses WHERE clause.
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
				if(isset($cond['glue']))
					$s .= ' ' . $cond['glue'];

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
			} elseif($value instanceof Result){
				$operator = ' IN ';
				$value = $this->escapeValue($value, Manager::SUBQUERY);
			} elseif(is_array($value) || $value instanceof Traversable){ // field IN (array)
				$value = ' IN ' . $this->escapeValue($value, Manager::ARR);
			} elseif($value instanceof Literal){ // field = SQL literal
				$operator = ' = ';
				$value = $this->escapeValue($value, Manager::LITERAL);
			} elseif($value instanceof DateTime){ // field = DateTime
				$operator = ' = ';
				$value = $this->escapeValue($value, Manager::DATETIME);
			} else{ // field = value
				$operator = ' = ';
				$value = $this->escapeValue($value);
			}
			$s = '(' . $field . $operator . $value . ')';
			if(isset($cond['glue']))
				$s .= ' '.$cond['glue'];

			$conditions[] = $s;

		}

		return "\nWHERE " . implode(' ', $conditions);
	}


	/**
	 * Parses ORDER BY clause.
	 * @return string
	 */
	protected function parseSorting(){
		$order = array();
		foreach($this->stmt->getSorting() as $rule){
			list($field, $type) = $rule;
			$order[] = $this->tryDelimite($field) . ($type !== null ? ' ' . $type : '');
		}
		return "\nORDER BY " . implode(', ', $order);
	}


	/**
	 * Parses GROUP BY clause.
	 * @return string
	 */
	protected function parseGrouping(){
		list($group, $having) = $this->stmt->getGrouping();
		return $this->tryDelimite("\nGROUP BY $group" . ($having !== null ? " HAVING $having" : ''));
	}


	/**
	 * Parses column name.
	 * @param string|array|Literal $field
	 * @param bool $table Parse table name.
	 * @return string
	 */
	protected function parseFieldName($field, $table = false){
		// preg_replace callback behaviour
		if(is_array($field))
			$field = $field[0];
		if($field instanceof Literal)
			return $field->value;

		$field = trim($field);

		if($field === '*')
			return $field;

		if(strpos($field, ' '))
			return $field;

		$field = str_replace(':', '', $field);

		if(strpos($field, '.') !== false || $table === true){
			$prefix = $this->stmt->getConnection()->getPrefix();
			$field = $prefix . $field;
		}

		return $this->stmt->getConnection()->getDriver()->escape($field, Manager::IDENTIFIER);
	}


	/**
	 * Applies LIMIT/OFFSET to SQL command.
	 * @param string $sql SQL command
	 * @return string
	 */
	protected function applyLimit($sql){
		list($limit, $offset) = $this->stmt->getLimit();

		if((int) $limit > 0){
			$sql .= "\nLIMIT " . (int) $limit;
			if((int) $offset > 0)
				$sql .= ' OFFSET ' . (int) $offset;
		}
		return $sql;
	}


	/**
	 * Escapes given value.
	 * @param mixed|array|Traversable $value
	 * @param string|array|null $type
	 * @return mixed|array
	 */
	protected function escapeValue($value, $type = null){
		if(!$type){
			// NULL type
			if($value === null)
				return 'NULL';

			// Array of values w/o types
			elseif(is_array($value)){
				foreach($value as $k => $v)
					$value[$k] = $this->escapeValue($v);
				return $value;
			}

			// Value w/o type
			else{
				if($value instanceof DateTime){
					return $this->escapeValue($value, Manager::DATETIME);
				} elseif($value instanceof Literal){
					return $value->value;
				} else{
					return is_numeric($value)
						? +$value
						: $this->stmt->getConnection()->getDriver()->escape($value, Manager::TEXT);
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
			if($type === Manager::INT){
				return (int) $value;
			} elseif($type === Manager::FLOAT){
				return (float) $value;
			} elseif($type === Manager::SUBQUERY && $value instanceof Result){
				return "(\n\t" . implode("\n\t", explode("\n", $value)) . "\n)";
			} elseif($type === Manager::ARR){
				$arr = $value instanceof Traversable ? iterator_to_array($value) : (array) $value;
				return '(' . implode(', ', $this->escapeValue($arr)) . ')';
			} elseif($type === Manager::LITERAL){
				return $value instanceof Literal ? $value->value : $value;
			} else{
				return $this->stmt->getConnection()->getDriver()->escape($value, $type);
			}
		}
	}


	/**
	 * Applies modifiers to expression.
	 * @param string $expr
	 * @param array $modifiers
	 * @param array $values
	 * @return string
	 */
	protected function applyModifiers($expr, array $modifiers, array $values){
		foreach($modifiers as &$mod){
			$mod = preg_quote("/$mod/");
		}
		$expr = $this->tryDelimite($expr);
		return preg_replace($modifiers, $values, $expr, 1);
	}


	/**
	 * Tries to delimite fields in given expression.
	 * @param string|Literal $expr
	 * @return string
	 */
	protected function tryDelimite($expr){
		if($expr instanceof Literal)
			return $expr->value;
		return preg_replace_callback('~:([a-z_\*][a-z0-9._\*]*)~i', array($this, 'parseFieldName'), $expr);
	}


}
