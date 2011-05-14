<?php


/**
 * Dummy Neevo driver/parser.
 */
class NeevoDriverParser extends NeevoParser implements INeevoDriver {


	/** @var NeevoStmtBase */
	protected $stmt;

	/** @var array */
	protected $clauses = array();


	function __construct(NeevoStmtBase $statement = null){
		if($statement !== null){
			return parent::__construct($statement);
		}
	}

	function connect(array $config){}
	function close(){}
	function free($resultSet){}
	function query($queryString){}
	function begin($savepoint = null){}
	function commit($savepoint = null){}
	function rollback($savepoint = null){}
	function fetch($resultSet){}
	function seek($resultSet, $offset){}
	function insertId(){}
	function rand(NeevoStmtBase $statement){}
	function rows($resultSet){}
	function affectedRows(){}
	function getPrimaryKey($table){}
	function getColumnTypes($resultSet, $table){}

	function escape($value, $type){
		switch($type){
			case Neevo::BOOL:
				return $value ? 'true' : 'false';

			case Neevo::TEXT:
				return "'$value'";

			case Neevo::IDENTIFIER:
				return ":$value";

			case Neevo::BINARY:
				return "bin:'$value'";

			case Neevo::DATETIME:
				return ($value instanceof DateTime) ? $value->format("'Y-m-d H:i:s'") : date("'Y-m-d H:i:s'", $value);

			default:
				throw new InvalidArgumentException('Unsupported data type.');
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

	public function parseFieldName($field){
		return parent::parseFieldName($field);
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