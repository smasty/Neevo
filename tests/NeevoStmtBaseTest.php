<?php

use PHPUnit_Framework_Assert as A;


/**
 * Tests for NeevoStmtBase.
 */
class NeevoStmtBaseTest extends PHPUnit_Framework_TestCase {


	/** @var DummyStmt */
	private $stmt;


	protected function setUp(){
		$this->stmt = new DummyStmt(new NeevoConnection(array(
					'driver' => 'Dummy'
				)));
	}


	protected function tearDown(){
		unset($this->stmt);
	}


	/**
	 * Test conditional statements - true.
	 */
	public function testConditionsTrue(){
		$this->stmt->if(true);
		A::assertFalse($this->stmt->_validateConditions());
	}


	/**
	 * Test conditional statements - false.
	 */
	public function testConditionsFalse(){
		$this->stmt->if(false);
		A::assertTrue($this->stmt->_validateConditions());
	}


	/**
	 * Test WHERE - default.
	 */
	public function testWhereDefault(){
		$this->stmt->where('one');
		A::assertEquals(array(array(
				'simple' => true,
				'field' => 'one',
				'value' => true,
				'glue' => 'AND'
			)), $this->stmt->getConditions());
	}


	/**
	 * Test WHERE - simple value.
	 */
	public function testWhereValue(){
		$this->stmt->where($f = 'two', $v = 'value');
		A::assertEquals(array(array(
				'simple' => true,
				'field' => $f,
				'value' => $v,
				'glue' => 'AND'
			)), $this->stmt->getConditions());
	}


	/**
	 * Test WHERE - modifiers.
	 */
	public function testWhereModifiers(){
		$this->stmt->where($e = 'one = %s AND two = %i', $a[] = 'one', $a[] = 2);
		A::assertEquals(array(array(
				'simple' => false,
				'expr' => $e,
				'modifiers' => array('%s', '%i'),
				'types' => array(Neevo::TEXT, Neevo::INT),
				'values' => $a,
				'glue' => 'AND'
			)), $this->stmt->getConditions());
	}


	/**
	 * Test WHERE - array.
	 */
	public function testWhereArray(){
		$this->stmt->where($a = array('one', 'value'));
		A::assertEquals(array(array(
				'simple' => true,
				'field' => $a[0],
				'value' => $a[1],
				'glue' => 'AND'
			)), $this->stmt->getConditions());
	}


	/**
	 * Test WHERE - do nothing.
	 */
	public function testWhereDoNothing(){
		$w = $this->stmt->getConditions();
		$this->stmt->if(false);
		$this->stmt->where('one', 'value');
		A::assertEquals($w, $this->stmt->getConditions());
	}


	/**
	 * Test WHERE - and() method.
	 */
	public function testWhereAnd(){
		$this->stmt->where('foo')->and('bar');
		$w = $this->stmt->getConditions();
		A::assertEquals('AND', $w[count($w) - 2]['glue']);
	}


	/**
	 * Test WHERE - or() method.
	 */
	public function testWhereOr(){
		$this->stmt->where('foo')->or('bar');
		$w = $this->stmt->getConditions();
		A::assertEquals('OR', $w[count($w) - 2]['glue']);
	}


	/**
	 * Test WHERE - and() / or() method - do nothing.
	 */
	public function testWhereAndOrDoNothing(){
		$this->stmt->if(true);
		$w = $this->stmt->getConditions();
		$this->stmt->if(false);
		$this->stmt->where('one')->and('two');
		A::assertEquals($w, $this->stmt->getConditions());
	}


	/**
	 * Test ORDER - simple.
	 */
	public function testOrderSimple(){
		$this->stmt->order($r = 'rule', $t = 'type');
		A::assertEquals(array(array($r, $t)), $this->stmt->getSorting());
	}


	/**
	 * Test ORDER - array.
	 */
	public function testOrderArray(){
		$this->stmt->order($a = array(
			'one' => '1',
			'two' => '2'
		));
		A::assertEquals(array(
			array('one', '1'),
			array('two', '2')), $this->stmt->getSorting());
	}


	/**
	 * Test ORDER - do nothing.
	 */
	public function testOrderDoNothing(){
		$s = $this->stmt->getSorting();
		$this->stmt->if(false);
		$this->stmt->order('one');
		A::assertEquals($s, $this->stmt->getSorting());
	}


	/**
	 * Test ORDER - orderBy() method - deprecated.
	 */
	public function testOrderBy(){
		@$this->stmt->orderBy('foo');
		A::assertEquals(array(array('foo', null)), $this->stmt->getSorting());
	}


	/**
	 * Test LIMIT.
	 */
	public function testLimit(){
		$this->stmt->limit(5);
		A::assertEquals(array(5, null), $this->stmt->getLimit());
	}


	/**
	 * Test LIMIT and OFFSET.
	 */
	public function testLimitOffset(){
		$this->stmt->setType(Neevo::STMT_SELECT);
		$this->stmt->limit(5, 10);
		A::assertEquals(array(5, 10), $this->stmt->getLimit());
	}


	/**
	 * Test LIMIT and OFFSET - do not set offset.
	 */
	public function testLimitOffsetNoOffset(){
		$this->stmt->limit(5, 10);
		A::assertEquals(array(5, null), $this->stmt->getLimit());
	}


	/**
	 * Test LIMIT - do nothing.
	 */
	public function testLimitDoNothing(){
		$l = $this->stmt->getLimit();
		$this->stmt->if(false);
		$this->stmt->limit(5);
		A::assertEquals($l, $this->stmt->getLimit());
	}


	/**
	 * Test RAND.
	 */
	public function testRandom(){
		$s = $this->stmt->getSorting();
		A::assertNotEquals($s, $this->stmt->rand());
	}


	/**
	 * Test RAND - do nothing.
	 */
	public function testRandomDoNothing(){
		$this->stmt->if(false);
		A::assertEquals($this->stmt, $this->stmt->rand());
	}


	/**
	 * Test parsing empty.
	 */
	public function testParse(){
		A::assertEmpty($this->stmt->parse());
	}


	/**
	 * Test dumping empty.
	 */
	public function testDump(){
		A::assertEmpty($this->stmt->dump(true));
	}


	/**
	 * Test dumping empty with echo.
	 */
	public function testDumpEcho(){
		ob_start();
		$this->stmt->dump();
		A::assertEmpty(ob_get_clean());
	}


	/**
	 * Test empty run.
	 */
	public function testRun(){
		A::assertFalse($this->stmt->run());
		A::assertTrue($this->stmt->isPerformed());
		A::assertLessThan(1, $this->stmt->time());
		A::assertFalse($this->stmt->exec());
	}


	/**
	 * Test getTable().
	 */
	public function testGetTable(){
		$this->stmt->setSource(':foo');
		A::assertEquals('foo', $this->stmt->getTable());
	}


	/**
	 * Test primary key detection.
	 */
	public function testGetPrimaryKey(){
		$this->stmt->setSource('table');
		A::assertEquals('id', $this->stmt->getPrimaryKey());
	}


	/**
	 * Test primary key detection without table name.
	 */
	public function testGetPrimaryKeyNull(){
		A::assertNull($this->stmt->getPrimaryKey());
	}


	/**
	 * Test primary key retrieve from cache.
	 */
	public function testGetPrimaryKeyCached(){
		$this->stmt->setSource('foo');
		$this->stmt->getConnection()->getCache()->store('foo_primaryKey', 'pk');
		A::assertEquals('pk', $this->stmt->getPrimaryKey());
	}


	/**
	 * Test foreign key detection.
	 */
	public function testGetForeignKey(){
		$this->stmt->setSource('foo');
		A::assertEquals('bar_id', $this->stmt->getForeignKey('bar'));
	}


	/**
	 * Test __toString().
	 */
	public function testToString(){
		A::assertEquals((string) $this->stmt, $this->stmt->parse());
	}


	/**
	 * Test conditional statements - if().
	 */
	public function testIf(){
		$this->stmt->if(true);
		A::assertFalse($this->stmt->_validateConditions());
	}


	/**
	 * Test conditional statements - if()->else().
	 */
	public function testIfElse(){
		$this->stmt->if(true)->else();
		A::assertTrue($this->stmt->_validateConditions());
	}


	/**
	 * Test conditional statements - if()->end().
	 */
	public function testIfEnd(){
		$this->stmt->if(true)->end();
		A::assertFalse($this->stmt->_validateConditions());
	}


	/**
	 * Test conditional statements - if() without argument.
	 */
	public function testIfException(){
		try{
			$this->stmt->if();
			$exc = false;
		} catch(InvalidArgumentException $e){
			$exc = true;
		}
		A::assertTrue($exc);
	}


	/**
	 * Test call to undefined method.
	 */
	public function testBadMethodCall(){
		try{
			$this->stmt->foobarbaz();
			$exc = false;
		} catch(BadMethodCallException $e){
			$exc = true;
		}
	}


}
