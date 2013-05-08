<?php
/**
 * Neevo - Tiny database layer for PHP. (http://neevo.smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * Copyright (c) 2012 Smasty (http://smasty.net)
 *
 */

namespace Neevo\Test;

use DummyResult;
use Neevo\Connection;
use Neevo\Manager;
use Neevo\Result;
use ReflectionMethod;
use ReflectionProperty;
use stdClass;


class ResultTest extends \PHPUnit_Framework_TestCase {


	/** @var Connection */
	private $connection;
	/** @var Result */
	private $result;


	protected function setUp(){
		$this->connection = new Connection(array(
				'driver' => 'Dummy'
			));
		$this->result = new Result($this->connection, 'foo');
	}


	protected function tearDown(){
		unset($this->stmt);
	}


	public function testInstantiationNoTable(){
		$this->setExpectedException('InvalidArgumentException', 'Missing select source.');
		new Result($this->connection);
	}


	public function testInstantiationNullSource(){
		$r = new Result($this->connection, $s = 'foo');
		$this->assertEquals($s, $r->getSource());
		$this->assertEquals(array('*'), $r->getColumns());
	}


	public function testInstantiationWrongSource(){
		$this->setExpectedException('InvalidArgumentException', 'Source must be a string or Neevo\\Result.');
		new Result($this->connection, new stdClass);
	}


	public function testInstantiationNoCols(){
		$this->setExpectedException('InvalidArgumentException', 'No columns given.');
		new Result($this->connection, array(), 'table');
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


	public function testJoinWrongSource(){
		$this->setExpectedException('InvalidArgumentException', 'must be a string, Neevo\\Literal or Neevo\\Result.');
		$this->result->join(new stdClass, true);
	}


	public function testLeftJoin(){
		$this->result->leftJoin($s = 'foo', $c = 'c');
		$this->assertEquals(
			array(array($s, $c, Manager::JOIN_LEFT)),
			$this->result->getJoins()
		);
	}


	public function testInnerJoin(){
		$this->result->innerJoin($s = 'foo', $c = 'c');
		$this->assertEquals(
			array(array($s, $c, Manager::JOIN_INNER)),
			$this->result->getJoins()
		);
	}


	public function testPage(){
		$this->result->page(2, 10);
		$this->assertEquals(array(10, 10), $this->result->getLimit());
	}


	public function testPageOne(){
		$this->result->page(1, 10);
		$this->assertEquals(array(10, 0), $this->result->getLimit());
	}


	public function testPageNonPositive(){
		$this->setExpectedException('InvalidArgumentException', 'Arguments must be positive integers.');
		$this->result->page(0, 0);
	}


	public function testFetch(){
		$row = $this->result->fetch()->toArray();
		$this->assertTrue(is_array($row) && count($row) === 3);
	}


	public function testFetchDetectTypes(){
		$result = new Result(new Connection('driver=Dummy&detectTypes=true'), 'foo');
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
			array($this->result->getConnection()->getDriver()->getRow(1)),
			array_map('iterator_to_array', $this->result->fetchAll(1, 1))
		);
	}


	public function testFetchSingle(){
		$this->assertTrue($this->result->fetchSingle() === '1');
	}


	public function testSingleDetectTypes(){
		$result = new Result(new Connection('driver=Dummy&detectTypes=true'), 'foo');
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
		$result = new Result($this->connection, 'col1, col2', 'table');
		$this->assertEquals(array(
			'1' => 'Jack York',
			'2' => 'Nora Frisbie',
			'3' => 'John Doe'
			), $result->fetchPairs('id', 'name'));
	}


	public function testFetchPairsNotDefinedRow(){
		$result = new Result($this->connection, 'col1, col2', 'table');
		$this->assertEquals(array('1', '2', '3'), array_keys($result->fetchPairs('id', null)));
	}


	public function testSeek(){
		$this->result->seek(2);
		$this->assertEquals(
			$this->result->getConnection()->getDriver()->getRow(2),
			$this->result->fetch()->toArray()
		);
	}


	public function testSeekOverflow(){
		$this->setExpectedException('Neevo\\NeevoException', 'Cannot seek to offset');
		$this->result->seek(5);
	}


	public function testGetIterator(){
		$this->assertInstanceOf('Neevo\ResultIterator', $this->result->getIterator());
	}


	public function testGetTable(){
		$result = new Result($this->connection, new Result($this->connection, 'foo'));
		$this->assertNull($result->getTable());
	}


	public function testCount(){
		$this->assertTrue(count($this->result) === 3);
		$this->assertTrue($this->result->rows() === 3);
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
			'id' => Manager::INT,
			'name' => Manager::TEXT,
			'mail' => Manager::TEXT
			), $r->getValue($this->result));
	}


	public function testDetectTypesError(){
		$r = new ReflectionProperty('Neevo\Result', 'columnTypes');
		$r->setAccessible(true);
		$this->result->getConnection()->getDriver()->setError('column-types');
		$this->result->detectTypes();
		$this->assertEquals(array(), $r->getValue($this->result));
	}


	public function testSetTypes(){
		$r = new ReflectionProperty('Neevo\Result', 'columnTypes');
		$r->setAccessible(true);
		$this->result->setTypes($t = array(
			'id' => Manager::INT,
			'name' => Manager::TEXT,
			'timestamp' => Manager::DATETIME
		));

		$this->assertEquals($t, $r->getValue($this->result));
	}


	public function testResolveTypeUnknown(){
		$r = new ReflectionMethod('Neevo\Result', 'resolveType');
		$r->setAccessible(true);
		$this->assertTrue($r->invoke($this->result, 'foo') === Manager::TEXT);
	}


	public function testConvertTypeString(){
		$r = new ReflectionMethod('Neevo\Result', 'convertType');
		$r->setAccessible(true);
		$this->assertTrue($r->invoke($this->result, 5, Manager::TEXT) === '5');
	}


	public function testConvertTypeInt(){
		$r = new ReflectionMethod('Neevo\Result', 'convertType');
		$r->setAccessible(true);
		$this->assertTrue($r->invoke($this->result, '5', Manager::INT) === 5);
	}


	public function testConvertTypeFloat(){
		$r = new ReflectionMethod('Neevo\Result', 'convertType');
		$r->setAccessible(true);
		$this->assertTrue($r->invoke($this->result, '5', Manager::FLOAT) === 5.0);
	}


	public function testConvertTypeBool(){
		$r = new ReflectionMethod('Neevo\Result', 'convertType');
		$r->setAccessible(true);
		$this->assertTrue($r->invoke($this->result, 'foo', Manager::BOOL));
	}


	public function testConvertTypeBinary(){
		$r = new ReflectionMethod('Neevo\Result', 'convertType');
		$r->setAccessible(true);
		$this->assertTrue($r->invoke($this->result, 'foo', Manager::BINARY) === 'bin:foo');
	}


	public function testConvertTypeNull(){
		$r = new ReflectionMethod('Neevo\Result', 'convertType');
		$r->setAccessible(true);
		$this->assertTrue($r->invoke($this->result, null, Manager::BINARY) === null);
	}


	public function testConvertTypeNoType(){
		$r = new ReflectionMethod('Neevo\Result', 'convertType');
		$r->setAccessible(true);
		$this->assertTrue($r->invoke($this->result, '5', 'auto') === '5');
	}


	public function testConvertTypeDateTimeZero(){
		$r = new ReflectionMethod('Neevo\Result', 'convertType');
		$r->setAccessible(true);
		$this->assertTrue($r->invoke($this->result, 0, Manager::DATETIME) === null);
	}


	public function testConvertTypeDateTimeTimestamp(){
		$r = new ReflectionMethod('Neevo\Result', 'convertType');
		$r->setAccessible(true);
		$t = time();
		$this->assertTrue($r->invoke($this->result, $t, Manager::DATETIME)->getTimestamp() === $t);
	}


	public function testConvertTypeDateTimeTimestampFormat(){
		$result = new Result(new Connection('driver=Dummy&formatDateTime=U'), 'foo');
		$r = new ReflectionMethod('Neevo\Result', 'convertType');
		$r->setAccessible(true);

		$this->assertTrue($r->invoke($result, $t = time(), Manager::DATETIME) === $t);
	}


	public function testConvertTypeDateTimeCustomFormat(){
		$result = new Result(new Connection('driver=Dummy&formatDateTime=Y-m-d'), 'foo');
		$r = new ReflectionMethod('Neevo\Result', 'convertType');
		$r->setAccessible(true);

		$this->assertTrue($r->invoke($result, $t = time(), Manager::DATETIME) === date('Y-m-d', $t));
	}


	public function testConvertTypeDateTimeCustomFormatString(){
		$result = new Result(new Connection('driver=Dummy&formatDateTime=Y-m-d'), 'foo');
		$r = new ReflectionMethod('Neevo\Result', 'convertType');
		$r->setAccessible(true);

		$this->assertTrue(
			$r->invoke($result, date('Y-m-d H:i:s', $t = time()), Manager::DATETIME) === date('Y-m-d', $t)
		);
	}


	public function testSetRowClass(){
		$this->assertInstanceOf('stdClass', $this->result->setRowClass('stdClass')->fetch());
	}


	public function testSetRowClassNoClass(){
		$this->setExpectedException('Neevo\\NeevoException', 'Cannot set row class');
		$this->result->setRowClass('NoClass');
	}


	public function testHasCircularReferences(){
		$this->setExpectedException('RuntimeException', 'Circular reference found');
		$this->result->leftJoin($this->result, 'foo')->dump(true);
	}


	public function testHasCircularReferencesDeeper(){
		$this->setExpectedException('RuntimeException', 'Circular reference found');
		$subquery = new Result($this->connection, $this->result);
		$this->result->leftJoin($subquery, 'foo')->dump(true);
	}


	public function testExplain(){
		$this->assertEquals(DummyResult::$dataExplain, $this->result->explain());
	}


}
