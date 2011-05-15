<?php

use PHPUnit_Framework_Assert as A;


/**
 * Tests for NeevoResult.
 */
class NeevoResultTest extends PHPUnit_Framework_TestCase {


	/** @var NeevoConnection */
	private $connection;
	/** @var NeevoResult */
	private $result;


	protected function setUp(){
		$this->connection = new NeevoConnection(array(
				'driver' => 'Dummy'
			));
		$this->result = new NeevoResult($this->connection, 'foo');
	}


	protected function tearDown(){
		unset($this->stmt);
	}


	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testInstantiationNoTable(){
		new NeevoResult($this->connection);
	}


	public function testInstantiationNullSource(){
		$r = new NeevoResult($this->connection, $s = 'foo');
		A::assertEquals($s, $r->getSource());
		A::assertEquals(array('*'), $r->getColumns());
	}


	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testInstantiationWrongSource(){
		new NeevoResult($this->connection, new stdClass);
	}


	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testInstantiationNoCols(){
		new NeevoResult($this->connection, array(), 'table');
	}


	public function testAlias(){
		$this->result->as($a = 'foo');
		A::assertEquals($a, $this->result->getAlias());
	}


	public function testGroup(){
		$this->result->group($g = 'bar');
		A::assertEquals(array($g, null), $this->result->getGrouping());
	}


	public function testGroupHaving(){
		$this->result->group($g = 'bar', $h = 'having');
		A::assertEquals(array($g, $h), $this->result->getGrouping());
	}


	public function testGroupDoNothing(){
		$g = $this->result->getGrouping();
		$this->result->if(false)
			->group('bar');
		A::assertEquals($g, $this->result->getGrouping());
	}


	public function testJoin(){
		$this->result->join($s = 'foo', $c = 'cond', $t = 'type');
		A::assertEquals(array(array($s, $c, $t)), $this->result->getJoins());
	}


	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testJoinWrongSource(){
		$this->result->join(new stdClass, true);
	}


	public function testLeftJoin(){
		$this->result->leftJoin($s = 'foo', $c = 'c');
		A::assertEquals(array(array($s, $c, Neevo::JOIN_LEFT)), $this->result->getJoins());
	}


	public function testInnerJoin(){
		$this->result->innerJoin($s = 'foo', $c = 'c');
		A::assertEquals(array(array($s, $c, Neevo::JOIN_INNER)), $this->result->getJoins());
	}


	public function testFetch(){
		$row = $this->result->fetch()->toArray();
		A::assertTrue(is_array($row) && count($row) === 3);
	}


	public function testFetchDetectTypes(){
		$result = new NeevoResult(new NeevoConnection('driver=Dummy&detectTypes=true'), 'foo');
		$r = $result->fetch()->toArray();
		A::assertInternalType('int', $r['id']);
		A::assertInternalType('string', $r['name']);
		A::assertInternalType('string', $r['mail']);
	}


	public function testFetchAll(){
		$rows = array_map('iterator_to_array', $this->result->fetchAll());
		A::assertTrue(is_array($rows) && count($rows === 3));
	}


	public function testFetchAllZeroLimit(){
		A::assertEquals(array(), $this->result->fetchAll(0));
	}


	public function testFetchAllOffset(){
		A::assertEquals(
			array($this->result->getConnection()->getDriver()->getRow(1)), array_map('iterator_to_array', $this->result->fetchAll(1, 1))
		);
	}


	public function testFetchSingle(){
		A::assertTrue($this->result->fetchSingle() === '1');
	}


	public function testSingleDetectTypes(){
		$result = new NeevoResult(new NeevoConnection('driver=Dummy&detectTypes=true'), 'foo');
		A::assertInternalType('int', $result->fetchSingle());
	}


	public function testFetchPairs(){
		A::assertEquals(array(
			'1' => 'Jack York',
			'2' => 'Nora Frisbie',
			'3' => 'John Doe'
			), $this->result->fetchPairs('id', 'name'));
	}


	public function testFetchPairsNotDefined(){
		$result = new NeevoResult($this->connection, 'col1, col2', 'table');
		A::assertEquals(array(
			'1' => 'Jack York',
			'2' => 'Nora Frisbie',
			'3' => 'John Doe'
			), $result->fetchPairs('id', 'name'));
	}


	public function testSeek(){
		$this->result->seek(2);
		A::assertEquals(
			$this->result->getConnection()->getDriver()->getRow(2), $this->result->fetch()->toArray()
		);
	}


	/**
	 * @expectedException NeevoException
	 */
	public function testSeekOverflow(){
		$this->result->seek(5);
	}


	public function testGetIterator(){
		A::assertInstanceOf('NeevoResultIterator', $this->result->getIterator());
	}


	public function testGetTable(){
		$result = new NeevoResult($this->connection, new NeevoResult($this->connection, 'foo'));
		A::assertNull($result->getTable());
	}


	public function testCount(){
		A::assertTrue(count($this->result) === 3);
	}


	public function testCountAggregate(){
		A::assertTrue($this->result->count('foo') === '1');
	}


	public function testAggregation(){
		A::assertTrue($this->result->aggregation('foo') === '1');
	}


	public function testMin(){
		A::assertTrue($this->result->min('foo') === '1');
	}


	public function testMax(){
		A::assertTrue($this->result->max('foo') === '1');
	}


	public function testSum(){
		A::assertTrue($this->result->sum('foo') === '1');
	}


	public function testDetectTypes(){
		$r = new ReflectionProperty('NeevoResult', 'columnTypes');
		$r->setAccessible(true);
		$this->result->detectTypes();
		A::assertEquals(array(
			'id' => Neevo::INT,
			'name' => Neevo::TEXT,
			'mail' => Neevo::TEXT
			), $r->getValue($this->result));
	}


	public function testSetTypes(){
		$r = new ReflectionProperty('NeevoResult', 'columnTypes');
		$r->setAccessible(true);
		$this->result->setTypes($t = array(
			'id' => Neevo::INT,
			'name' => Neevo::TEXT,
			'timestamp' => Neevo::DATETIME
		));

		A::assertEquals($t, $r->getValue($this->result));
	}


	public function testGetReferencedRow(){
		$row = new NeevoRow(array('id' => 1), $this->result);
		$referenced = $this->result->getReferencedRow('foo', $row, 'id')->toArray();
		A::assertTrue($referenced['id'] === '1');
	}


	public function testGetReferencedRowNull(){
		$row = new NeevoRow(array('id' => 1), $this->result);
		A::assertNull($this->result->getReferencedRow('foo', $row, 'row_id'));
	}


	public function testConvertTypeString(){
		$r = new ReflectionMethod('NeevoResult', 'convertType');
		$r->setAccessible(true);
		A::assertTrue($r->invoke($this->result, 5, Neevo::TEXT) === '5');
	}


	public function testConvertTypeInt(){
		$r = new ReflectionMethod('NeevoResult', 'convertType');
		$r->setAccessible(true);
		A::assertTrue($r->invoke($this->result, '5', Neevo::INT) === 5);
	}


	public function testConvertTypeFloat(){
		$r = new ReflectionMethod('NeevoResult', 'convertType');
		$r->setAccessible(true);
		A::assertTrue($r->invoke($this->result, '5', Neevo::FLOAT) === 5.0);
	}


	public function testConvertTypeBool(){
		$r = new ReflectionMethod('NeevoResult', 'convertType');
		$r->setAccessible(true);
		A::assertTrue($r->invoke($this->result, 'foo', Neevo::BOOL));
	}


	public function testConvertTypeBinary(){
		$r = new ReflectionMethod('NeevoResult', 'convertType');
		$r->setAccessible(true);
		A::assertTrue($r->invoke($this->result, 'foo', Neevo::BINARY) === 'bin:foo');
	}


	public function testConvertTypeNull(){
		$r = new ReflectionMethod('NeevoResult', 'convertType');
		$r->setAccessible(true);
		A::assertTrue($r->invoke($this->result, null, Neevo::BINARY) === null);
	}


	public function testConvertTypeNoType(){
		$r = new ReflectionMethod('NeevoResult', 'convertType');
		$r->setAccessible(true);
		A::assertTrue($r->invoke($this->result, '5', 'auto') === '5');
	}


	public function testConvertTypeDateTimeZero(){
		$r = new ReflectionMethod('NeevoResult', 'convertType');
		$r->setAccessible(true);
		A::assertTrue($r->invoke($this->result, 0, Neevo::DATETIME) === null);
	}


	public function testConvertTypeDateTimeTimestamp(){
		$r = new ReflectionMethod('NeevoResult', 'convertType');
		$r->setAccessible(true);
		$t = time();
		A::assertTrue($r->invoke($this->result, $t, Neevo::DATETIME)->getTimestamp() === $t);
	}


	public function testConvertTypeDateTimeTimestampFormat(){
		$result = new NeevoResult(new NeevoConnection('driver=Dummy&formatDateTime=U'), 'foo');
		$r = new ReflectionMethod('NeevoResult', 'convertType');
		$r->setAccessible(true);

		A::assertTrue($r->invoke($result, $t = time(), Neevo::DATETIME) === $t);
	}


	public function testConvertTypeDateTimeCustomFormat(){
		$result = new NeevoResult(new NeevoConnection('driver=Dummy&formatDateTime=Y-m-d'), 'foo');
		$r = new ReflectionMethod('NeevoResult', 'convertType');
		$r->setAccessible(true);

		A::assertTrue($r->invoke($result, $t = time(), Neevo::DATETIME) === date('Y-m-d', $t));
	}


	public function testConvertTypeDateTimeCustomFormatString(){
		$result = new NeevoResult(new NeevoConnection('driver=Dummy&formatDateTime=Y-m-d'), 'foo');
		$r = new ReflectionMethod('NeevoResult', 'convertType');
		$r->setAccessible(true);

		A::assertTrue($r->invoke($result, date('Y-m-d H:i:s', $t = time()), Neevo::DATETIME) === date('Y-m-d', $t));
	}


	public function testSetRowClass(){
		A::assertInstanceOf('stdClass', $this->result->setRowClass('stdClass')->fetch());
	}


	/**
	 * @expectedException NeevoException
	 */
	public function testSetRowClassNoClass(){
		$this->result->setRowClass('NoClass');
	}


	/**
	 * @expectedException RuntimeException
	 */
	public function testHasCircularReferences(){
		$this->markTestIncomplete();
		$this->result->leftJoin($this->result, 'foo')->dump(true);
	}


	/**
	 * @expectedException RuntimeException
	 */
	public function testHasCircularReferencesDeeper(){
		$this->markTestIncomplete();
		$subquery = new NeevoResult($this->connection, $this->result);
		$this->result->leftJoin($subquery, 'foo')->dump(true);
	}


}
