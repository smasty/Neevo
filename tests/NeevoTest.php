<?php

use PHPUnit_Framework_Assert as A;


/**
 * Tests for Neevo.
 */
class NeevoTest extends PHPUnit_Framework_TestCase {

	/** @var Neevo */
	private $neevo;


	protected function setUp(){
		$this->neevo = new Neevo('driver=Dummy&lazy=1');
	}


	protected function tearDown(){
		unset($this->neevo);
	}


	public function testConnect(){
		$neevo = new Neevo('driver=Dummy', new NeevoCacheSession);
		A::assertInstanceOf('NeevoDriverDummy', $neevo->getConnection()->getDriver());
		A::assertInstanceOf('NeevoCacheSession', $neevo->getConnection()->getCache());

		$r = new ReflectionProperty('NeevoConnection', 'observers');
		$r->setAccessible(true);
		A::assertTrue($r->getValue($neevo->getConnection())->contains($neevo));
	}


	public function testBeginTransaction(){
		$this->neevo->begin();
		A::assertTrue($this->neevo->getConnection()->getDriver()->inTransaction);
	}


	public function testCommitTransaction(){
		$this->neevo->begin();
		$this->neevo->commit();
		A::assertFalse($this->neevo->getConnection()->getDriver()->inTransaction);
	}


	public function testRollbackTransaction(){
		$this->neevo->begin();
		$this->neevo->rollback();
		A::assertFalse($this->neevo->getConnection()->getDriver()->inTransaction);
	}


	public function testSelect(){
		$res = $this->neevo->select($c = 'col', $t = 'table');
		A::assertInstanceOf('NeevoResult', $res);
		A::assertEquals(Neevo::STMT_SELECT, $res->getType());
		A::assertEquals(array($c), $res->getColumns());
		A::assertEquals($t, $res->getSource());
		A::assertTrue($res->getConnection() === $this->neevo->getConnection());
	}


	public function testInsert(){
		$ins = $this->neevo->insert($t = 'table', $v = array('val1', 'val2'));
		A::assertInstanceOf('NeevoStmt', $ins);
		A::assertEquals(Neevo::STMT_INSERT, $ins->getType());
		A::assertEquals($t, $ins->getTable());
		A::assertEquals($v, $ins->getValues());
	}


	public function testUpdate(){
		$upd = $this->neevo->update($t = 'table', $d = array('val1', 'val2'));
		A::assertInstanceOf('NeevoStmt', $upd);
		A::assertEquals(Neevo::STMT_UPDATE, $upd->getType());
		A::assertEquals($t, $upd->getTable());
		A::assertEquals($d, $upd->getValues());
	}


	public function testDelete(){
		$del = $this->neevo->delete($t = 'table');
		A::assertEquals(Neevo::STMT_DELETE, $del->getType());
		A::assertInstanceOf('NeevoStmt', $del);
		A::assertEquals($t, $del->getTable());
	}


	public function testAttachObserver(){
		$o = new DummyObserver;
		$this->neevo->attachObserver($o);
		$this->neevo->notifyObservers(1);
		A::assertTrue($o->isNotified($e));
		A::assertEquals(1, $e);
		$this->neevo->detachObserver($o);
	}


	public function testUpdateStatus(){
		$r = $this->neevo->select('foo');
		$sql = (string) $r;
		$r->run();
		A::assertEquals($sql, $this->neevo->getLast());
		A::assertEquals(1, $this->neevo->getQueries());
	}


	public function testHighlightSql(){
		A::assertEquals(
			"<pre style=\"color:#555\" class=\"sql-dump\"><strong style=\"color:#e71818\">SELECT</strong> * \n<strong style=\"color:#e71818\">FROM</strong> `table` \n<strong style=\"color:#e71818\">WHERE</strong> <strong style=\"color:#d59401\">RAND</strong>() = <em style=\"color:#008000\">'John Doe'</em>; <em style=\"color:#999\">/* comment */</em></pre>\n",
			$v=Neevo::highlightSql("SELECT * FROM `table` WHERE RAND() = 'John Doe'; /* comment */")
		);
	}

	public function testDestructor(){
		$closed = $this->neevo->getConnection()->getDriver()->closed;
		$this->neevo->__destruct();
		A::assertEquals(!$closed, $this->neevo->getConnection()->getDriver()->closed);
	}


}
