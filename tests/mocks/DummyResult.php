<?php

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


	public function __construct($queryString, Neevo\Drivers\DummyDriver $driver){
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