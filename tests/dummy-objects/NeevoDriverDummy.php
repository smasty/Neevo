<?php


/**
 * Dummy Neevo driver.
 */
class NeevoDriverDummy implements INeevoDriver {


	private $cursor = 0;

	private $unbuffered = false;

	private $data = array(
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

	public $inTransaction = false;

	public $closed = false;


	public function __construct(){

	}


	public function connect(array $config){
		$this->unbuffered = $config['unbuffered'];
	}


	public function close(){
		$this->closed = true;
		return true;
	}


	public function free($resultSet){
		$this->cursor = 0;
		return true;
	}


	public function query($queryString){
		if($queryString){
			return new DummyResult($queryString, $this);
		}
		return false;
	}


	public function begin($savepoint = null){
		$this->inTransaction = true;
	}


	public function commit($savepoint = null){
		$this->inTransaction = false;
	}


	public function rollback($savepoint = null){
		$this->inTransaction = false;
	}


	public function fetch($resultSet){
		if(!$resultSet){
			return false;
		}
		if($counter < count($this->data)){
			return $this->data[$this->cursor++];
		}
		return false;
	}


	public function seek($resultSet, $offset){
		if($this->unbuffered){
			throw new NeevoDriverException('Cannot seek on unbuffered result.');
		}
		if($resultSet && $offset < count($this->data)){
			$this->cursor = $offset;
			return true;
		}
		return false;
	}


	public function insertId(){
		return 4;
	}


	public function rand(NeevoStmtBase $statement){

	}


	public function rows($resultSet){
		if($this->unbuffered){
			throw new NeevoDriverException('Cannot count rows on unbuffered result.');
		}
		return $resultSet ? 3 : false;
	}


	public function affectedRows(){
		return 1;
	}


	public function escape($value, $type){
		return $value;
	}


	public function unescape($value, $type){
		if($type === Neevo::BINARY){
			return "bin:$value";
		}
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


	public function getRow($i = null){
		if($i === null){
			return $this->data;
		}
		if(isset($this->data[$i])){
			return $this->data[$i];
		}
		return false;
	}


}


class DummyResult {


	private $queryString, $driver;


	public function __construct($queryString, NeevoDriverDummy $driver){
		$this->queryString = $queryString;
		$this->driver= $driver;
	}


	public function __destruct(){
		$this->driver->free($this);
	}


}