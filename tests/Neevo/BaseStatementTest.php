<?php


class DummyStmt extends Neevo\BaseStatement {

}


/**
 * Tests for Neevo\BaseStatement.
 */
class BaseStatementTest extends PHPUnit_Framework_TestCase {


	/** @var DummyStmt */
	private $stmt;


	protected function setUp(){
		$this->stmt = new DummyStmt(new Neevo\Connection(array(
					'driver' => 'Dummy'
				)));
	}


	protected function tearDown(){
		unset($this->stmt);
	}


	public function testWhereDefault(){
		$this->stmt->where('one');
		$this->assertEquals(array(array(
				'simple' => true,
				'field' => 'one',
				'value' => true,
				'glue' => 'AND'
			)), $this->stmt->getConditions());
	}


	public function testWhereValue(){
		$this->stmt->where($f = 'two', $v = new DummyStmt(new Neevo\Connection('driver=Dummy')));
		$this->assertEquals(array(array(
				'simple' => true,
				'field' => $f,
				'value' => $v,
				'glue' => 'AND'
			)), $this->stmt->getConditions());
	}


	public function testWhereModifiers(){
		$this->stmt->where($e = 'one = %s AND two = %i AND three = %sub', $a[] = 'one', $a[] = 2,
			$a[] = new DummyStmt(new Neevo\Connection('driver=Dummy')));
		$this->assertEquals(array(array(
				'simple' => false,
				'expr' => $e,
				'modifiers' => array('%s', '%i', '%sub'),
				'types' => array(Neevo\Manager::TEXT, Neevo\Manager::INT, Neevo\Manager::SUBQUERY),
				'values' => $a,
				'glue' => 'AND'
			)), $this->stmt->getConditions());
	}


	public function testWhereAssocArray(){
		$this->stmt->where(array($k = 'key' => $v = 'val'));
		$this->assertEquals(array(array(
				'simple' => true,
				'field' => $k,
				'value' => $v,
				'glue' => 'AND'
			)), $this->stmt->getConditions());
	}


	public function testWhereDoNothing(){
		$w = $this->stmt->getConditions();
		$this->stmt->if(false);
		$this->stmt->where('one', 'value');
		$this->assertEquals($w, $this->stmt->getConditions());
	}


	public function testWhereAnd(){
		$this->stmt->where('foo')->and('bar');
		$w = $this->stmt->getConditions();
		$this->assertEquals('AND', $w[count($w) - 2]['glue']);
	}


	public function testWhereOr(){
		$this->stmt->where('foo')->or('bar');
		$w = $this->stmt->getConditions();
		$this->assertEquals('OR', $w[count($w) - 2]['glue']);
	}


	public function testWhereAndOrDoNothing(){
		$this->stmt->if(true);
		$w = $this->stmt->getConditions();
		$this->stmt->if(false);
		$this->stmt->where('one')->and('two');
		$this->assertEquals($w, $this->stmt->getConditions());
	}


	public function testWhereOrFirstCondition(){
		$this->stmt->or($f = 'test', $v = false);
		$this->assertEquals(array(
			array(
				'simple' => true,
				'field' => $f,
				'value' => $v,
				'glue' => 'AND'
			)
		), $this->stmt->getConditions());
	}


	public function testOrderSimple(){
		$this->stmt->order($r = 'rule', $t = 'type');
		$this->assertEquals(array(array($r, $t)), $this->stmt->getSorting());
	}


	public function testOrderArray(){
		$this->stmt->order($a = array(
			'one' => '1',
			'two' => '2'
		));
		$this->assertEquals(array(
			array('one', '1'),
			array('two', '2')), $this->stmt->getSorting());
	}


	public function testOrderDoNothing(){
		$s = $this->stmt->getSorting();
		$this->stmt->if(false);
		$this->stmt->order('one');
		$this->assertEquals($s, $this->stmt->getSorting());
	}


	public function testLimit(){
		$this->stmt->limit(5);
		$this->assertEquals(array(5, null), $this->stmt->getLimit());
	}


	public function testLimitOffset(){
		$r = new ReflectionProperty('DummyStmt', 'type');
		$r->setAccessible(true);
		$r->setValue($this->stmt, Neevo\Manager::STMT_SELECT);

		$this->stmt->limit(5, 10);
		$this->assertEquals(array(5, 10), $this->stmt->getLimit());
	}


	public function testLimitOffsetNoOffset(){
		$this->stmt->limit(5, 10);
		$this->assertEquals(array(5, null), $this->stmt->getLimit());
	}


	public function testLimitDoNothing(){
		$l = $this->stmt->getLimit();
		$this->stmt->if(false);
		$this->stmt->limit(5);
		$this->assertEquals($l, $this->stmt->getLimit());
	}


	public function testRandom(){
		$s = $this->stmt->getSorting();
		$this->assertNotEquals($s, $this->stmt->rand());
	}


	public function testRandomDoNothing(){
		$this->stmt->if(false);
		$this->assertEquals($this->stmt, $this->stmt->rand());
	}


	public function testParse(){
		$this->assertEmpty($this->stmt->parse());
	}


	public function testDump(){
		$this->assertEquals("\n", $this->stmt->dump(true));
	}


	public function testDumpEcho(){
		ob_start();
		$this->stmt->dump();
		$this->assertEquals("\n", ob_get_clean());
	}


	public function testRun(){
		$this->assertFalse($this->stmt->run());
		$this->assertTrue($this->stmt->isPerformed());
		$this->assertLessThan(1, $this->stmt->getTime());
		$this->assertFalse($this->stmt->exec());
	}


	public function testRunQueryError(){
		$this->setExpectedException('Neevo\\NeevoException');
		$this->stmt->getConnection()->getDriver()->setError('query');
		$this->stmt->run();
	}


	public function testGetTable(){
		$r = new ReflectionProperty('DummyStmt', 'source');
		$r->setAccessible(true);
		$r->setValue($this->stmt, ':foo');

		$this->assertEquals('foo', $this->stmt->getTable());
	}


	public function testGetPrimaryKey(){
		$r = new ReflectionProperty('DummyStmt', 'source');
		$r->setAccessible(true);
		$r->setValue($this->stmt, 'table');

		$this->assertEquals('id', $this->stmt->getPrimaryKey());
	}


	public function testGetPrimaryKeyNull(){
		$this->assertNull($this->stmt->getPrimaryKey());
	}


	public function testGetPrimaryKeyError(){
		$r = new ReflectionProperty('DummyStmt', 'source');
		$r->setAccessible(true);
		$r->setValue($this->stmt, 'table');

		$this->stmt->getConnection()->getDriver()->setError('primary-key');
		$this->assertNull($this->stmt->getPrimaryKey());
	}


	public function testGetPrimaryKeyCached(){
		$r = new ReflectionProperty('DummyStmt', 'source');
		$r->setAccessible(true);
		$r->setValue($this->stmt, 'foo');

		$this->stmt->getConnection()->getCache()->store('foo_primaryKey', 'pk');
		$this->assertEquals('pk', $this->stmt->getPrimaryKey());
	}


	public function testToString(){
		$this->assertEquals((string) $this->stmt, $this->stmt->parse());
	}


	public function testIf(){
		$this->stmt->if(true);

		$r = new ReflectionMethod($this->stmt, 'validateConditions');
		$r->setAccessible(true);
		$this->assertFalse($r->invoke($this->stmt));
	}


	public function testIfElse(){
		$this->stmt->if(true)->else();

		$r = new ReflectionMethod($this->stmt, 'validateConditions');
		$r->setAccessible(true);
		$this->assertTrue($r->invoke($this->stmt));
	}


	public function testIfEnd(){
		$this->stmt->if(true)->end();

		$r = new ReflectionMethod($this->stmt, 'validateConditions');
		$r->setAccessible(true);
		$this->assertFalse($r->invoke($this->stmt));
	}


	public function testIfException(){
		$this->setExpectedException('InvalidArgumentException');
		$this->stmt->if();
	}


	public function testBadMethodCall(){
		$this->setExpectedException('BadMethodCallException');
		$this->stmt->foobarbaz();
	}


	public function testDetachObserver(){
		$o = new DummyObserver;
		$this->stmt->attachObserver($o, 1);
		$this->stmt->detachObserver($o);
		$this->stmt->notifyObservers(1);
		$this->assertFalse($o->isNotified());
	}


}
