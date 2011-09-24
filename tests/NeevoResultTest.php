<?php


/**
 * Tests for Neevo\Result.
 */
class ResultTest extends PHPUnit_Framework_TestCase {


	/** @var Neevo\Connection */
	private $connection;
	/** @var Neevo\Result */
	private $result;


	protected function setUp(){
		$this->connection = new Neevo\Connection(array(
				'driver' => 'Dummy'
			));
		$this->result = new Neevo\Result($this->connection, 'foo');
	}


	protected function tearDown(){
		unset($this->stmt);
	}


	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testInstantiationNoTable(){
		new Neevo\Result($this->connection);
	}


	public function testInstantiationNullSource(){
		$r = new Neevo\Result($this->connection, $s = 'foo');
		$this->assertEquals($s, $r->getSource());
		$this->assertEquals(array('*'), $r->getColumns());
	}


	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testInstantiationWrongSource(){
		new Neevo\Result($this->connection, new stdClass);
	}


	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testInstantiationNoCols(){
		new Neevo\Result($this->connection, array(), 'table');
	}


	public function testAlias(){
		$this->result->as($a = 'foo');
		$this->assertEquals($a, $this->result->getAlias());
	}


	public function testGroup(){
		$this->result->group($g = 'bar');
		$this->assertEquals(array($g, null), $this->result->getGrouping());
	}


	public function testGroupHaving(){
		$this->result->group($g = 'bar', $h = 'having');
		$this->assertEquals(array($g, $h), $this->result->getGrouping());
	}


	public function testGroupDoNothing(){
		$g = $this->result->getGrouping();
		$this->result->if(false)
			->group('bar');
		$this->assertEquals($g, $this->result->getGrouping());
	}


	public function testJoin(){
		$this->result->join($s = 'foo', $c = 'cond', $t = 'type');
		$this->assertEquals(array(array($s, $c, $t)), $this->result->getJoins());
	}


	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testJoinWrongSource(){
		$this->result->join(new stdClass, true);
	}


	public function testLeftJoin(){
		$this->result->leftJoin($s = 'foo', $c = 'c');
		$this->assertEquals(array(array($s, $c, Neevo\Manager::JOIN_LEFT)), $this->result->getJoins());
	}


	public function testInnerJoin(){
		$this->result->innerJoin($s = 'foo', $c = 'c');
		$this->assertEquals(array(array($s, $c, Neevo\Manager::JOIN_INNER)), $this->result->getJoins());
	}


	public function testFetch(){
		$row = $this->result->fetch()->toArray();
		$this->assertTrue(is_array($row) && count($row) === 3);
	}


	public function testFetchDetectTypes(){
		$result = new Neevo\Result(new Neevo\Connection('driver=Dummy&detectTypes=true'), 'foo');
		$r = $result->fetch()->toArray();
		$this->assertInternalType('int', $r['id']);
		$this->assertInternalType('string', $r['name']);
		$this->assertInternalType('string', $r['mail']);
	}


	public function testFetchAll(){
		$rows = array_map('iterator_to_array', $this->result->fetchAll());
		$this->assertTrue(is_array($rows) && count($rows === 3));
	}


	public function testFetchAllZeroLimit(){
		$this->assertEquals(array(), $this->result->fetchAll(0));
	}


	public function testFetchAllOffset(){
		$this->assertEquals(
			array($this->result->getConnection()->getDriver()->getRow(1)), array_map('iterator_to_array', $this->result->fetchAll(1, 1))
		);
	}


	public function testFetchSingle(){
		$this->assertTrue($this->result->fetchSingle() === '1');
	}


	public function testSingleDetectTypes(){
		$result = new Neevo\Result(new Neevo\Connection('driver=Dummy&detectTypes=true'), 'foo');
		$this->assertInternalType('int', $result->fetchSingle());
	}


	public function testFetchPairs(){
		$this->assertEquals(array(
			'1' => 'Jack York',
			'2' => 'Nora Frisbie',
			'3' => 'John Doe'
			), $this->result->fetchPairs('id', 'name'));
	}


	public function testFetchPairsNotDefined(){
		$result = new Neevo\Result($this->connection, 'col1, col2', 'table');
		$this->assertEquals(array(
			'1' => 'Jack York',
			'2' => 'Nora Frisbie',
			'3' => 'John Doe'
			), $result->fetchPairs('id', 'name'));
	}


	public function testSeek(){
		$this->result->seek(2);
		$this->assertEquals(
			$this->result->getConnection()->getDriver()->getRow(2), $this->result->fetch()->toArray()
		);
	}


	/**
	 * @expectedException Neevo\NeevoException
	 */
	public function testSeekOverflow(){
		$this->result->seek(5);
	}


	public function testGetIterator(){
		$this->assertInstanceOf('Neevo\ResultIterator', $this->result->getIterator());
	}


	public function testGetTable(){
		$result = new Neevo\Result($this->connection, new Neevo\Result($this->connection, 'foo'));
		$this->assertNull($result->getTable());
	}


	public function testCount(){
		$this->assertTrue(count($this->result) === 3);
	}


	public function testCountAggregate(){
		$this->assertTrue($this->result->count('foo') === '1');
	}


	public function testAggregation(){
		$this->assertTrue($this->result->aggregation('foo') === '1');
	}


	public function testMin(){
		$this->assertTrue($this->result->min('foo') === '1');
	}


	public function testMax(){
		$this->assertTrue($this->result->max('foo') === '1');
	}


	public function testSum(){
		$this->assertTrue($this->result->sum('foo') === '1');
	}


	public function testDetectTypes(){
		$r = new ReflectionProperty('Neevo\Result', 'columnTypes');
		$r->setAccessible(true);
		$this->result->detectTypes();
		$this->assertEquals(array(
			'id' => Neevo\Manager::INT,
			'name' => Neevo\Manager::TEXT,
			'mail' => Neevo\Manager::TEXT
			), $r->getValue($this->result));
	}


	public function testSetTypes(){
		$r = new ReflectionProperty('Neevo\Result', 'columnTypes');
		$r->setAccessible(true);
		$this->result->setTypes($t = array(
			'id' => Neevo\Manager::INT,
			'name' => Neevo\Manager::TEXT,
			'timestamp' => Neevo\Manager::DATETIME
		));

		$this->assertEquals($t, $r->getValue($this->result));
	}


	public function testConvertTypeString(){
		$r = new ReflectionMethod('Neevo\Result', 'convertType');
		$r->setAccessible(true);
		$this->assertTrue($r->invoke($this->result, 5, Neevo\Manager::TEXT) === '5');
	}


	public function testConvertTypeInt(){
		$r = new ReflectionMethod('Neevo\Result', 'convertType');
		$r->setAccessible(true);
		$this->assertTrue($r->invoke($this->result, '5', Neevo\Manager::INT) === 5);
	}


	public function testConvertTypeFloat(){
		$r = new ReflectionMethod('Neevo\Result', 'convertType');
		$r->setAccessible(true);
		$this->assertTrue($r->invoke($this->result, '5', Neevo\Manager::FLOAT) === 5.0);
	}


	public function testConvertTypeBool(){
		$r = new ReflectionMethod('Neevo\Result', 'convertType');
		$r->setAccessible(true);
		$this->assertTrue($r->invoke($this->result, 'foo', Neevo\Manager::BOOL));
	}


	public function testConvertTypeBinary(){
		$r = new ReflectionMethod('Neevo\Result', 'convertType');
		$r->setAccessible(true);
		$this->assertTrue($r->invoke($this->result, 'foo', Neevo\Manager::BINARY) === 'bin:foo');
	}


	public function testConvertTypeNull(){
		$r = new ReflectionMethod('Neevo\Result', 'convertType');
		$r->setAccessible(true);
		$this->assertTrue($r->invoke($this->result, null, Neevo\Manager::BINARY) === null);
	}


	public function testConvertTypeNoType(){
		$r = new ReflectionMethod('Neevo\Result', 'convertType');
		$r->setAccessible(true);
		$this->assertTrue($r->invoke($this->result, '5', 'auto') === '5');
	}


	public function testConvertTypeDateTimeZero(){
		$r = new ReflectionMethod('Neevo\Result', 'convertType');
		$r->setAccessible(true);
		$this->assertTrue($r->invoke($this->result, 0, Neevo\Manager::DATETIME) === null);
	}


	public function testConvertTypeDateTimeTimestamp(){
		$r = new ReflectionMethod('Neevo\Result', 'convertType');
		$r->setAccessible(true);
		$t = time();
		$this->assertTrue($r->invoke($this->result, $t, Neevo\Manager::DATETIME)->getTimestamp() === $t);
	}


	public function testConvertTypeDateTimeTimestampFormat(){
		$result = new Neevo\Result(new Neevo\Connection('driver=Dummy&formatDateTime=U'), 'foo');
		$r = new ReflectionMethod('Neevo\Result', 'convertType');
		$r->setAccessible(true);

		$this->assertTrue($r->invoke($result, $t = time(), Neevo\Manager::DATETIME) === $t);
	}


	public function testConvertTypeDateTimeCustomFormat(){
		$result = new Neevo\Result(new Neevo\Connection('driver=Dummy&formatDateTime=Y-m-d'), 'foo');
		$r = new ReflectionMethod('Neevo\Result', 'convertType');
		$r->setAccessible(true);

		$this->assertTrue($r->invoke($result, $t = time(), Neevo\Manager::DATETIME) === date('Y-m-d', $t));
	}


	public function testConvertTypeDateTimeCustomFormatString(){
		$result = new Neevo\Result(new Neevo\Connection('driver=Dummy&formatDateTime=Y-m-d'), 'foo');
		$r = new ReflectionMethod('Neevo\Result', 'convertType');
		$r->setAccessible(true);

		$this->assertTrue($r->invoke($result, date('Y-m-d H:i:s', $t = time()), Neevo\Manager::DATETIME) === date('Y-m-d', $t));
	}


	public function testSetRowClass(){
		$this->assertInstanceOf('stdClass', $this->result->setRowClass('stdClass')->fetch());
	}


	/**
	 * @expectedException Neevo\NeevoException
	 */
	public function testSetRowClassNoClass(){
		$this->result->setRowClass('NoClass');
	}


	/**
	 * @expectedException RuntimeException
	 */
	public function testHasCircularReferences(){
		$this->result->leftJoin($this->result, 'foo')->dump(true);
	}


	/**
	 * @expectedException RuntimeException
	 */
	public function testHasCircularReferencesDeeper(){
		$subquery = new Neevo\Result($this->connection, $this->result);
		$this->result->leftJoin($subquery, 'foo')->dump(true);
	}


}
