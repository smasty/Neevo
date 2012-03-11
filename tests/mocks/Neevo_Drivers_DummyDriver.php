<?php

namespace Neevo\Drivers;

use Neevo,
	Neevo\DriverException;

/**
 * Dummy Neevo driver.
 */
class DummyDriver implements Neevo\IDriver {

	private $unbuffered = false,
			$connected = false,
			$closed,
			$transactions = array(),
			$performed = array();



	const TRANSACTION_OPEN = 1,
		TRANSACTION_COMMIT = 2,
		TRANSACTION_ROLLBACK = 4;


	public function __construct(){

	}


	public function connect(array $config){
		$this->unbuffered = isset($config['unbuffered']) ? $config['unbuffered'] : false;
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
		if($queryString){
			$this->performed[] = $queryString;
			return new \DummyResult($queryString, $this);
		}
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
			throw new DriverException("Invalid savepoint '$savepoint'.");
	}


	public function rollback($savepoint = null){
		if(isset($this->transactions[$savepoint]))
			$this->transactions[$savepoint] = self::TRANSACTION_ROLLBACK;
		elseif($savepoint === null)
			$this->transactions[count($this->transactions)-1] = self::TRANSACTION_ROLLBACK;
		else
			throw new DriverException("Invalid savepoint '$savepoint'.");
	}


	public function fetch($resultSet){
		return $resultSet->fetch();
	}


	public function seek($resultSet, $offset){
		if($this->unbuffered)
			throw new DriverException('Cannot seek on unbuffered result.');
		return $resultSet->seek($offset);
	}


	public function getInsertId(){
		return 4;
	}


	public function randomizeOrder(Neevo\BaseStatement $statement){
		$statement->order('RANDOM()');
	}


	public function getNumRows($resultSet){
		if($this->unbuffered)
			throw new DriverException('Cannot count rows on unbuffered result.');
		return $resultSet ? 3 : false;
	}


	public function getAffectedRows(){
		return 1;
	}


	public function escape($value, $type){
		return $value;
	}


	public function unescape($value, $type){
		if($type === Neevo\Manager::BINARY)
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
			return \DummyResult::$data;
		if(isset(\DummyResult::$data[$i]))
			return \DummyResult::$data[$i];
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


	public function performed(){
		return $this->performed;
	}


}