<?php


/**
 * Dummy Neevo driver with custom parser.
 */
class NeevoDriverDummyParser extends NeevoParser implements INeevoDriver {


	public function __construct(NeevoStmtBase $statement = null){
		if($statement instanceof NeevoStmtBase){
			parent::__construct($statement);
		}
	}


	public function connect(array $config){

	}


	public function close(){
		return true;
	}


	public function free($resultSet){
		return true;
	}


	public function query($queryString){
		return (bool) $queryString;
	}


	public function begin($savepoint = null){

	}


	public function commit($savepoint = null){

	}


	public function rollback($savepoint = null){

	}


	public function fetch($resultSet){
		if($resultSet){
			return array(
				array(
					'id' => '1',
					'name' => 'Jack York',
					'mail' => 'jack.york@mail.tld'
				),
				array(
					'id' => '2',
					'name' => 'Nora Frisbie',
					'mail' => 'nora.friesbie@mail.tld'
				),
				array(
					'id' => '3',
					'name' => 'John Doe',
					'mail' => 'john.doe@mail.tld'
				)
			);
		}
		return false;
	}


	public function seek($resultSet, $offset){
		return ($resultSet && $offset > 0);
	}


	public function insertId(){
		return 4;
	}


	public function rand(NeevoStmtBase $statement){

	}


	public function rows($resultSet){
		return $resultSet ? 3 : false;
	}


	public function affectedRows(){
		return 1;
	}


	public function escape($value, $type){
		return $value;
	}


	public function unescape($value, $type){
		return $value;
	}


	public function getPrimaryKey($table){
		return 'id';
	}


	public function getColumnTypes($resultSet, $table){
		return array(
			'id' => 'int',
			'name' => 'text',
			'mail' => 'text'
		);
	}


}
