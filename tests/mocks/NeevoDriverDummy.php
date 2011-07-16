<?php


/**
 * Dummy Neevo driver.
 */
class NeevoDriverDummy implements INeevoDriver {

	private $unbuffered = false,
			$connected = false,
			$closed,
			$transactions = array();



	const TRANSACTION_OPEN = 1,
		TRANSACTION_COMMIT = 2,
		TRANSACTION_ROLLBACK = 4;


	public function __construct(){

	}


	public function connect(array $config){
		$this->unbuffered = $config['unbuffered'];
		$this->connected = true;
	}


	public function closeConnection(){
		$this->connected = false;
		return $this->closed = true;
	}


	public function freeResultSet($resultSet){
		$resultSet = null;
		return true;
	}


	public function runQuery($queryString){
		if($queryString)
			return new DummyResult($queryString, $this);
		return false;
	}


	public function beginTransaction($savepoint = null){
		$this->transactions[$savepoint] = self::TRANSACTION_OPEN;
	}


	public function commit($savepoint = null){
		if(isset($this->transactions[$savepoint]))
			$this->transactions[$savepoint] = self::TRANSACTION_COMMIT;
		elseif($savepoint === null)
			$this->transactions[count($this->transactions)-1] = self::TRANSACTION_COMMIT;
		else
			throw new NeevoDriverException("Invalid savepoint '$savepoint'.");
	}


	public function rollback($savepoint = null){
		if(isset($this->transactions[$savepoint]))
			$this->transactions[$savepoint] = self::TRANSACTION_ROLLBACK;
		elseif($savepoint === null)
			$this->transactions[count($this->transactions)-1] = self::TRANSACTION_ROLLBACK;
		else
			throw new NeevoDriverException("Invalid savepoint '$savepoint'.");
	}


	public function fetch($resultSet){
		return $resultSet->fetch();
	}


	public function seek($resultSet, $offset){
		if($this->unbuffered)
			throw new NeevoDriverException('Cannot seek on unbuffered result.');
		return $resultSet->seek($offset);
	}


	public function getInsertId(){
		return 4;
	}


	public function randomizeOrder(NeevoStmtBase $statement){
		$statement->order('RANDOM()');
	}


	public function getNumRows($resultSet){
		if($this->unbuffered)
			throw new NeevoDriverException('Cannot count rows on unbuffered result.');
		return $resultSet ? 3 : false;
	}


	public function getAffectedRows(){
		return 1;
	}


	public function escape($value, $type){
		return $value;
	}


	public function unescape($value, $type){
		if($type === Neevo::BINARY)
			return "bin:$value";
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
		if($i === null)
			return DummyResult::$data;
		if(isset(DummyResult::$data[$i]))
			return DummyResult::$data[$i];
		return false;
	}


	public function isClosed(){
		return (bool) $this->closed;
	}


	public function isConnected(){
		return (bool) $this->connected;
	}


	public function transactionState(){
		return end($this->transactions);
	}


}


class DummyResult {


	private $queryString, $driver, $cursor = 0;

	public static $data = array(
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


	public function __construct($queryString, NeevoDriverDummy $driver){
		$this->queryString = $queryString;
		$this->driver= $driver;
	}


	public function __destruct(){
		$this->driver->freeResultSet($this);
	}


	public function fetch(){
		if($this->cursor >= count(self::$data))
			return false;
		return self::$data[$this->cursor++];
	}


	public function seek($offset){
		if($offset >= count(self::$data))
			return false;
		$this->cursor = $offset;
		return true;
	}

}