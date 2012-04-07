<?php

class DummyResult {


	private $queryString, $driver, $cursor = 0, $explain = false;

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


	public static $dataExplain = array(
		array('foo' => 'bar'),
		array('foo' => 'baz'),
		array('foo' => 'qux')
	);


	public function __construct($queryString, Neevo\Drivers\DummyDriver $driver){
		$this->queryString = $queryString;
		$this->driver= $driver;
		if(strtolower(substr($queryString, 0, 7)) == 'explain')
			$this->explain = true;
	}


	public function __destruct(){
		$this->driver->freeResultSet($this);
	}


	public function fetch(){
		if($this->cursor >= count($this->explain ? self::$dataExplain : self::$data))
			return false;
		return $this->explain ? self::$dataExplain[$this->cursor++] : self::$data[$this->cursor++];
	}


	public function seek($offset){
		if($offset >= count($this->explain ? self::$dataExplain : self::$data))
			return false;
		$this->cursor = $offset;
		return true;
	}

}