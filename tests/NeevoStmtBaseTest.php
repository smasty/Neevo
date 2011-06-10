<?php

use PHPUnit_Framework_Assert as A;


class DummyStmt extends NeevoStmtBase {

}


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


	public function testWhereDefault(){
		$this->stmt->where('one');
		A::assertEquals(array(array(
				'simple' => true,
				'field' => 'one',
				'value' => true,
				'glue' => 'AND'
			)), $this->stmt->getConditions());
	}


	public function testWhereValue(){
		$this->stmt->where($f = 'two', $v = new DummyStmt(new NeevoConnection('driver=Dummy')));
		A::assertEquals(array(array(
				'simple' => true,
				'field' => $f,
				'value' => $v,
				'glue' => 'AND'
			)), $this->stmt->getConditions());
	}


	public function testWhereModifiers(){
		$this->stmt->where($e = 'one = %s AND two = %i AND three = %sub', $a[] = 'one', $a[] = 2,
			$a[] = new DummyStmt(new NeevoConnection('driver=Dummy')));
		A::assertEquals(array(array(
				'simple' => false,
				'expr' => $e,
				'modifiers' => array('%s', '%i', '%sub'),
				'types' => array(Neevo::TEXT, Neevo::INT, Neevo::SUBQUERY),
				'values' => $a,
				'glue' => 'AND'
			)), $this->stmt->getConditions());
	}


	public function testWhereArray(){
		$this->stmt->where($a = array('one', 'value'));
		A::assertEquals(array(array(
				'simple' => true,
				'field' => $a[0],
				'value' => $a[1],
				'glue' => 'AND'
			)), $this->stmt->getConditions());
	}


	public function testWhereDoNothing(){
		$w = $this->stmt->getConditions();
		$this->stmt->if(false);
		$this->stmt->where('one', 'value');
		A::assertEquals($w, $this->stmt->getConditions());
	}


	public function testWhereAnd(){
		$this->stmt->where('foo')->and('bar');
		$w = $this->stmt->getConditions();
		A::assertEquals('AND', $w[count($w) - 2]['glue']);
	}


	public function testWhereOr(){
		$this->stmt->where('foo')->or('bar');
		$w = $this->stmt->getConditions();
		A::assertEquals('OR', $w[count($w) - 2]['glue']);
	}


	public function testWhereAndOrDoNothing(){
		$this->stmt->if(true);
		$w = $this->stmt->getConditions();
		$this->stmt->if(false);
		$this->stmt->where('one')->and('two');
		A::assertEquals($w, $this->stmt->getConditions());
	}


	public function testOrderSimple(){
		$this->stmt->order($r = 'rule', $t = 'type');
		A::assertEquals(array(array($r, $t)), $this->stmt->getSorting());
	}


	public function testOrderArray(){
		$this->stmt->order($a = array(
			'one' => '1',
			'two' => '2'
		));
		A::assertEquals(array(
			array('one', '1'),
			array('two', '2')), $this->stmt->getSorting());
	}


	public function testOrderDoNothing(){
		$s = $this->stmt->getSorting();
		$this->stmt->if(false);
		$this->stmt->order('one');
		A::assertEquals($s, $this->stmt->getSorting());
	}


	public function testLimit(){
		$this->stmt->limit(5);
		A::assertEquals(array(5, null), $this->stmt->getLimit());
	}


	public function testLimitOffset(){
		$r = new ReflectionProperty('DummyStmt', 'type');
		$r->setAccessible(true);
		$r->setValue($this->stmt, Neevo::STMT_SELECT);

		$this->stmt->limit(5, 10);
		A::assertEquals(array(5, 10), $this->stmt->getLimit());
	}


	public function testLimitOffsetNoOffset(){
		$this->stmt->limit(5, 10);
		A::assertEquals(array(5, null), $this->stmt->getLimit());
	}


	public function testLimitDoNothing(){
		$l = $this->stmt->getLimit();
		$this->stmt->if(false);
		$this->stmt->limit(5);
		A::assertEquals($l, $this->stmt->getLimit());
	}


	public function testRandom(){
		$s = $this->stmt->getSorting();
		A::assertNotEquals($s, $this->stmt->rand());
	}


	public function testRandomDoNothing(){
		$this->stmt->if(false);
		A::assertEquals($this->stmt, $this->stmt->rand());
	}


	public function testParse(){
		A::assertEmpty($this->stmt->parse());
	}


	public function testDump(){
		A::assertEquals("\n", $this->stmt->dump(true));
	}


	public function testDumpEcho(){
		ob_start();
		$this->stmt->dump();
		A::assertEquals("\n", ob_get_clean());
	}


	public function testRun(){
		A::assertFalse($this->stmt->run());
		A::assertTrue($this->stmt->isPerformed());
		A::assertLessThan(1, $this->stmt->getTime());
		A::assertFalse($this->stmt->exec());
	}


	public function testGetTable(){
		$r = new ReflectionProperty('DummyStmt', 'source');
		$r->setAccessible(true);
		$r->setValue($this->stmt, ':foo');

		A::assertEquals('foo', $this->stmt->getTable());
	}


	public function testGetPrimaryKey(){
		$r = new ReflectionProperty('DummyStmt', 'source');
		$r->setAccessible(true);
		$r->setValue($this->stmt, 'table');

		A::assertEquals('id', $this->stmt->getPrimaryKey());
	}


	public function testGetPrimaryKeyNull(){
		A::assertNull($this->stmt->getPrimaryKey());
	}


	public function testGetPrimaryKeyCached(){
		$r = new ReflectionProperty('DummyStmt', 'source');
		$r->setAccessible(true);
		$r->setValue($this->stmt, 'foo');

		$this->stmt->getConnection()->getCache()->store('foo_primaryKey', 'pk');
		A::assertEquals('pk', $this->stmt->getPrimaryKey());
	}


	public function testGetForeignKey(){
		$r = new ReflectionProperty('DummyStmt', 'source');
		$r->setAccessible(true);
		$r->setValue($this->stmt, 'foo');

		A::assertEquals('bar_id', $this->stmt->getForeignKey('bar'));
	}


	public function testToString(){
		A::assertEquals((string) $this->stmt, $this->stmt->parse());
	}


	public function testIf(){
		$this->stmt->if(true);

		$r = new ReflectionMethod($this->stmt, '_validateConditions');
		$r->setAccessible(true);
		A::assertFalse($r->invoke($this->stmt));
	}


	public function testIfElse(){
		$this->stmt->if(true)->else();

		$r = new ReflectionMethod($this->stmt, '_validateConditions');
		$r->setAccessible(true);
		A::assertTrue($r->invoke($this->stmt));
	}


	public function testIfEnd(){
		$this->stmt->if(true)->end();

		$r = new ReflectionMethod($this->stmt, '_validateConditions');
		$r->setAccessible(true);
		A::assertFalse($r->invoke($this->stmt));
	}


	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testIfException(){
		$this->stmt->if();
	}


	/**
	 * @expectedException BadMethodCallException
	 */
	public function testBadMethodCall(){
		$this->stmt->foobarbaz();
	}


}
