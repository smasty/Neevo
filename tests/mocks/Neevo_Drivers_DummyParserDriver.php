<?php

namespace Neevo\Drivers;

use Neevo,
	Neevo\DriverException;

/**
 * Dummy Neevo driver/parser.
 */
class DummyParserDriver extends Neevo\Parser implements Neevo\IDriver {


	/** @var Neevo\BaseStatement */
	protected $stmt;

	/** @var array */
	protected $clauses = array();


	function __construct(Neevo\BaseStatement $statement = null){
		if($statement !== null)
			return parent::__construct($statement);
	}

	function connect(array $config){}
	function closeConnection(){}
	function freeResultSet($resultSet){}
	function runQuery($queryString){}
	function beginTransaction($savepoint = null){}
	function commit($savepoint = null){}
	function rollback($savepoint = null){}
	function fetch($resultSet){}
	function seek($resultSet, $offset){}
	function getInsertId(){}
	function randomizeOrder(Neevo\BaseStatement $statement){}
	function getNumRows($resultSet){}
	function getAffectedRows(){}
	function getPrimaryKey($table){}
	function getColumnTypes($resultSet, $table){}

	function escape($value, $type){
		switch($type){
			case Neevo\Manager::BOOL:
				return $value ? 'true' : 'false';

			case Neevo\Manager::TEXT:
				return "'$value'";

			case Neevo\Manager::IDENTIFIER:
				return "`$value`";

			case Neevo\Manager::BINARY:
				return "bin:'$value'";

			case Neevo\Manager::DATETIME:
				return ($value instanceof \DateTime) ? $value->format("'Y-m-d H:i:s'") : date("'Y-m-d H:i:s'", $value);

			default:
				throw new \InvalidArgumentException('Unsupported data type.');
				break;
		}
	}

	function unescape($value, $type){
		return $value;
	}

	public function applyLimit($sql){
		return parent::applyLimit($sql);
	}

	public function escapeValue($value, $type = null){
		return parent::escapeValue($value, $type);
	}

	public function applyModifiers($expr, array $modifiers, array $values){
		return parent::applyModifiers($expr, $modifiers, $values);
	}

	public function parse(){
		return parent::parse();
	}

	public function parseDeleteStmt(){
		return parent::parseDeleteStmt();
	}

	public function parseFieldName($field, $table = false){
		return parent::parseFieldName($field, $table);
	}

	public function parseGrouping(){
		return parent::parseGrouping();
	}

	public function parseInsertStmt(){
		return parent::parseInsertStmt();
	}

	public function parseSelectStmt(){
		return parent::parseSelectStmt();
	}

	public function parseSorting(){
		return parent::parseSorting();
	}

	public function parseSource(){
		return parent::parseSource();
	}

	public function parseUpdateStmt(){
		return parent::parseUpdateStmt();
	}

	public function parseWhere(){
		return parent::parseWhere();
	}

	public function tryDelimite($expr){
		return parent::tryDelimite($expr);
	}


}